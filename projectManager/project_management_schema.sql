-- Project Management System Database Schema
-- For issue tracking, agile project management, and collaboration
-- Compatible with existing 'brugere' table
-- PostgreSQL version

-- Projects table
CREATE TABLE IF NOT EXISTS projects (
    id SERIAL PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    project_key VARCHAR(20) UNIQUE NOT NULL, -- Short identifier like PROJ-001
    description TEXT,
    project_manager_id INTEGER REFERENCES brugere(id),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'completed', 'on_hold', 'cancelled')),
    start_date DATE,
    end_date DATE,
    budget NUMERIC(15,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER REFERENCES brugere(id)
);

-- Project members table (many-to-many relationship between projects and users)
CREATE TABLE IF NOT EXISTS project_members (
    id SERIAL PRIMARY KEY,
    project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES brugere(id) ON DELETE CASCADE,
    role VARCHAR(50) DEFAULT 'member', -- 'admin', 'manager', 'developer', 'tester', 'member'
    permissions TEXT, -- JSON string for specific permissions
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, user_id)
);

-- Issue/Task types
CREATE TABLE IF NOT EXISTS issue_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'Bug', 'Feature', 'Task', 'Story', 'Epic'
    description TEXT,
    icon VARCHAR(50),
    color VARCHAR(7), -- Hex color code
    is_active BOOLEAN DEFAULT TRUE
);

-- Issue priorities
CREATE TABLE IF NOT EXISTS issue_priorities (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'Critical', 'High', 'Medium', 'Low'
    level INTEGER NOT NULL UNIQUE, -- 1 = highest, 5 = lowest
    color VARCHAR(7), -- Hex color code
    is_active BOOLEAN DEFAULT TRUE
);

-- Issue statuses
CREATE TABLE IF NOT EXISTS issue_statuses (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    status_type VARCHAR(20) NOT NULL CHECK (status_type IN ('todo', 'in_progress', 'review', 'done', 'cancelled')),
    project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
    position INTEGER DEFAULT 0, -- For ordering statuses
    color VARCHAR(7), -- Hex color code
    is_active BOOLEAN DEFAULT TRUE
);

-- Issues/Tasks table
CREATE TABLE IF NOT EXISTS issues (
    id SERIAL PRIMARY KEY,
    issue_key VARCHAR(50) UNIQUE NOT NULL, -- PROJ-123 format
    project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
    parent_id INTEGER REFERENCES issues(id), -- For subtasks and epics
    title VARCHAR(255) NOT NULL,
    description TEXT,
    issue_type_id INTEGER REFERENCES issue_types(id),
    status_id INTEGER REFERENCES issue_statuses(id),
    priority_id INTEGER REFERENCES issue_priorities(id),
    assignee_id INTEGER REFERENCES brugere(id),
    reporter_id INTEGER REFERENCES brugere(id),
    story_points INTEGER, -- For agile estimation
    original_estimate INTEGER, -- In minutes
    remaining_estimate INTEGER, -- In minutes
    time_spent INTEGER DEFAULT 0, -- In minutes
    due_date DATE,
    resolution VARCHAR(100), -- 'Fixed', 'Won''t Fix', 'Duplicate', etc.
    resolution_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER REFERENCES brugere(id),
    updated_by INTEGER REFERENCES brugere(id)
);

-- Issue labels (for categorization)
CREATE TABLE IF NOT EXISTS project_labels (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(7), -- Hex color code
    project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE(name, project_id)
);

-- Issue-Label relationship
CREATE TABLE IF NOT EXISTS issue_project_labels (
    id SERIAL PRIMARY KEY,
    issue_id INTEGER REFERENCES issues(id) ON DELETE CASCADE,
    label_id INTEGER REFERENCES project_labels(id) ON DELETE CASCADE,
    UNIQUE(issue_id, label_id)
);

-- Issue comments
CREATE TABLE IF NOT EXISTS issue_comments (
    id SERIAL PRIMARY KEY,
    issue_id INTEGER REFERENCES issues(id) ON DELETE CASCADE,
    author_id INTEGER REFERENCES brugere(id),
    content TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE, -- For internal team comments
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Issue attachments
CREATE TABLE IF NOT EXISTS issue_attachments (
    id SERIAL PRIMARY KEY,
    issue_id INTEGER REFERENCES issues(id) ON DELETE CASCADE,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path TEXT NOT NULL,
    file_size BIGINT,
    mime_type VARCHAR(100),
    uploaded_by INTEGER REFERENCES brugere(id),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sprints (for agile methodology)
CREATE TABLE IF NOT EXISTS sprints (
    id SERIAL PRIMARY KEY,
    project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    goal TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'planned' CHECK (status IN ('planned', 'active', 'completed', 'cancelled')),
    velocity INTEGER, -- Points completed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER REFERENCES brugere(id)
);

-- Sprint-Issue relationship
CREATE TABLE IF NOT EXISTS sprint_issues (
    id SERIAL PRIMARY KEY,
    sprint_id INTEGER REFERENCES sprints(id) ON DELETE CASCADE,
    issue_id INTEGER REFERENCES issues(id) ON DELETE CASCADE,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(sprint_id, issue_id)
);

-- Time tracking/work logs
CREATE TABLE IF NOT EXISTS work_logs (
    id SERIAL PRIMARY KEY,
    issue_id INTEGER REFERENCES issues(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES brugere(id),
    time_spent INTEGER NOT NULL, -- In minutes
    work_date DATE NOT NULL,
    description TEXT,
    started_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Issue watchers (users following an issue)
CREATE TABLE IF NOT EXISTS issue_watchers (
    id SERIAL PRIMARY KEY,
    issue_id INTEGER REFERENCES issues(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES brugere(id) ON DELETE CASCADE,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(issue_id, user_id)
);

-- Issue activity/history
CREATE TABLE IF NOT EXISTS issue_activity (
    id SERIAL PRIMARY KEY,
    issue_id INTEGER REFERENCES issues(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES brugere(id),
    action VARCHAR(50) NOT NULL, -- 'created', 'updated', 'commented', 'assigned', etc.
    field_name VARCHAR(50), -- Which field was changed
    old_value TEXT,
    new_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notifications for users
CREATE TABLE IF NOT EXISTS project_notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES brugere(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- 'issue_assigned', 'issue_updated', 'comment_added', etc.
    title VARCHAR(255) NOT NULL,
    message TEXT,
    related_issue_id INTEGER REFERENCES issues(id) ON DELETE CASCADE,
    related_project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Teams/Groups for better organization
CREATE TABLE IF NOT EXISTS teams (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    lead_id INTEGER REFERENCES brugere(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER REFERENCES brugere(id)
);

-- Team members
CREATE TABLE IF NOT EXISTS team_members (
    id SERIAL PRIMARY KEY,
    team_id INTEGER REFERENCES teams(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES brugere(id) ON DELETE CASCADE,
    role VARCHAR(50) DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(team_id, user_id)
);

-- Project-Team relationship
CREATE TABLE IF NOT EXISTS project_teams (
    id SERIAL PRIMARY KEY,
    project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
    team_id INTEGER REFERENCES teams(id) ON DELETE CASCADE,
    UNIQUE(project_id, team_id)
);

-- Boards (Kanban/Scrum boards)
CREATE TABLE IF NOT EXISTS boards (
    id SERIAL PRIMARY KEY,
    project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(20) DEFAULT 'kanban' CHECK (type IN ('kanban', 'scrum')),
    configuration TEXT, -- JSON for board settings
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER REFERENCES brugere(id)
);

-- Custom fields for projects/issues
CREATE TABLE IF NOT EXISTS custom_fields (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    field_type VARCHAR(20) NOT NULL CHECK (field_type IN ('text', 'number', 'date', 'select', 'multi_select', 'boolean')),
    options TEXT, -- JSON for select options
    is_required BOOLEAN DEFAULT FALSE,
    applies_to VARCHAR(20) NOT NULL CHECK (applies_to IN ('project', 'issue')),
    project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE, -- NULL means global
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Custom field values
CREATE TABLE IF NOT EXISTS custom_field_values (
    id SERIAL PRIMARY KEY,
    field_id INTEGER REFERENCES custom_fields(id) ON DELETE CASCADE,
    entity_type VARCHAR(20) NOT NULL CHECK (entity_type IN ('project', 'issue')),
    entity_id INTEGER NOT NULL, -- project_id or issue_id
    value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default data
INSERT INTO issue_types (name, description, icon, color) VALUES
('Bug', 'A problem that impairs or prevents the functions of the product', 'bug', '#d73a49'),
('Feature', 'A new feature or enhancement request', 'star', '#28a745'),
('Task', 'A general task that needs to be completed', 'check', '#6f42c1'),
('Story', 'A user story for agile development', 'book', '#007bff'),
('Epic', 'A large user story that can be broken down into smaller stories', 'bookmark', '#fd7e14')
ON CONFLICT (name) DO NOTHING;

INSERT INTO issue_priorities (name, level, color) VALUES
('Critical', 1, '#dc3545'),
('High', 2, '#fd7e14'),
('Medium', 3, '#ffc107'),
('Low', 4, '#28a745'),
('Lowest', 5, '#6c757d')
ON CONFLICT (level) DO NOTHING;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_issues_project_id ON issues(project_id);
CREATE INDEX IF NOT EXISTS idx_issues_assignee_id ON issues(assignee_id);
CREATE INDEX IF NOT EXISTS idx_issues_reporter_id ON issues(reporter_id);
CREATE INDEX IF NOT EXISTS idx_issues_status_id ON issues(status_id);
CREATE INDEX IF NOT EXISTS idx_issues_priority_id ON issues(priority_id);
CREATE INDEX IF NOT EXISTS idx_issues_created_at ON issues(created_at);
CREATE INDEX IF NOT EXISTS idx_issue_comments_issue_id ON issue_comments(issue_id);
CREATE INDEX IF NOT EXISTS idx_issue_activity_issue_id ON issue_activity(issue_id);
CREATE INDEX IF NOT EXISTS idx_work_logs_issue_id ON work_logs(issue_id);
CREATE INDEX IF NOT EXISTS idx_work_logs_user_id ON work_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_project_notifications_user_id ON project_notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_project_notifications_is_read ON project_notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_project_members_project_id ON project_members(project_id);
CREATE INDEX IF NOT EXISTS idx_project_members_user_id ON project_members(user_id);

-- Create update triggers for timestamp fields
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_projects_updated_at BEFORE UPDATE ON projects
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_issues_updated_at BEFORE UPDATE ON issues
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_issue_comments_updated_at BEFORE UPDATE ON issue_comments
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_custom_field_values_updated_at BEFORE UPDATE ON custom_field_values
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Create function to generate issue keys
CREATE OR REPLACE FUNCTION generate_issue_key(project_key TEXT)
RETURNS TEXT AS $$
DECLARE
    next_number INTEGER;
    issue_key TEXT;
BEGIN
    -- Get the next number for this project
    SELECT COALESCE(MAX(CAST(SUBSTRING(issues.issue_key FROM LENGTH(project_key) + 2) AS INTEGER)), 0) + 1
    INTO next_number
    FROM issues
    JOIN projects ON issues.project_id = projects.id
    WHERE projects.project_key = generate_issue_key.project_key;
    
    -- Generate the issue key
    issue_key := project_key || '-' || next_number;
    
    RETURN issue_key;
END;
$$ LANGUAGE plpgsql;

-- Comments describing the schema
COMMENT ON TABLE projects IS 'Main projects table for organizing work';
COMMENT ON TABLE issues IS 'Issues, tasks, bugs, and stories for project management';
COMMENT ON TABLE sprints IS 'Sprint planning for agile development';
COMMENT ON TABLE work_logs IS 'Time tracking for issues';
COMMENT ON TABLE teams IS 'Team organization and management';
COMMENT ON TABLE project_notifications IS 'User notifications system';
COMMENT ON TABLE boards IS 'Kanban and Scrum boards for visual project management';
