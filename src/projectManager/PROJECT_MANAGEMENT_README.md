# Project Management System Database Schema Documentation

## Overview
This database schema provides comprehensive support for issue tracking, agile project management, and team collaboration. It integrates with your existing `brugere` (users) table and follows PostgreSQL best practices.

## Core Tables

### 1. Projects (`projects`)
- **Purpose**: Main container for organizing work
- **Key Features**:
  - Unique project keys (e.g., "PROJ", "WEB", "API")
  - Project manager assignment
  - Status tracking (active, completed, on_hold, cancelled)
  - Budget tracking
  - Date range management

### 2. Issues (`issues`)
- **Purpose**: Central table for tasks, bugs, features, stories, and epics
- **Key Features**:
  - Auto-generated issue keys (e.g., PROJ-123)
  - Hierarchical structure (parent/child relationships)
  - Comprehensive metadata (type, status, priority, assignee)
  - Time tracking (estimates and actual time)
  - Story points for agile estimation

### 3. Project Management Features

#### Issue Types (`issue_types`)
- Bug, Feature, Task, Story, Epic
- Customizable with colors and icons

#### Issue Priorities (`issue_priorities`)
- Critical, High, Medium, Low, Lowest
- Numerical levels for sorting

#### Issue Statuses (`issue_statuses`)
- Project-specific customizable statuses
- Categorized by type (todo, in_progress, review, done, cancelled)

### 4. Agile Features

#### Sprints (`sprints`)
- Sprint planning and management
- Goal setting and velocity tracking
- Issue assignment to sprints

#### Boards (`boards`)
- Kanban and Scrum board support
- Visual project management

### 5. Collaboration Features

#### Teams (`teams`)
- Organize users into teams
- Team leads and roles
- Project-team assignments

#### Comments (`issue_comments`)
- Issue discussions
- Internal vs external comments

#### Notifications (`notifications`)
- Real-time updates
- Issue assignments and changes

#### Watchers (`issue_watchers`)
- Users can follow specific issues

### 6. Time Tracking

#### Work Logs (`work_logs`)
- Detailed time tracking per issue
- Daily work logs
- Integration with issue time estimates

### 7. Customization

#### Custom Fields (`custom_fields`, `custom_field_values`)
- Extensible field system
- Support for various field types
- Project-specific or global fields

#### Labels (`labels`, `issue_labels`)
- Flexible tagging system
- Color-coded organization

## Integration with Existing System

The schema integrates seamlessly with your existing `brugere` table:
- All user references use `brugere.id`
- Maintains existing user management
- Extends functionality without breaking changes

## Usage Examples

### Creating a New Project
```sql
INSERT INTO projects (project_name, project_key, description, project_manager_id, created_by)
VALUES ('Website Redesign', 'WEB', 'Complete redesign of company website', 1, 1);
```

### Adding Team Members
```sql
INSERT INTO project_members (project_id, user_id, role)
VALUES (1, 2, 'developer'), (1, 3, 'designer');
```

### Creating an Issue
```sql
INSERT INTO issues (issue_key, project_id, title, description, issue_type_id, priority_id, assignee_id, reporter_id)
VALUES (
    generate_issue_key('WEB'), -- Auto-generates WEB-1, WEB-2, etc.
    1,
    'Fix navigation menu',
    'The navigation menu is not responsive on mobile devices',
    1, -- Bug type
    2, -- High priority
    2, -- Assigned to user ID 2
    1  -- Reported by user ID 1
);
```

### Setting up Default Statuses for a Project
```sql
INSERT INTO issue_statuses (name, status_type, project_id, position) VALUES
('To Do', 'todo', 1, 1),
('In Progress', 'in_progress', 1, 2),
('Code Review', 'review', 1, 3),
('Testing', 'review', 1, 4),
('Done', 'done', 1, 5);
```

## Performance Considerations

The schema includes several indexes for optimal performance:
- Issue lookups by project, assignee, reporter
- Comment and activity lookups by issue
- Notification lookups by user
- Time-based queries on creation dates

## Security Features

- Foreign key constraints ensure data integrity
- User-based access control through project membership
- Audit trail through activity logging
- Soft delete capabilities where appropriate

## Next Steps

1. **Run the Schema**: Execute `project_management_schema.sql` on your database
2. **Create Default Data**: Set up initial projects, teams, and users
3. **Configure Permissions**: Establish role-based access control
4. **Integrate with UI**: Build or adapt existing interfaces
5. **Set up Notifications**: Implement email or in-app notifications

## API Considerations

The schema is designed to support REST API development with:
- Clear entity relationships
- JSON support for flexible configuration
- Timestamp tracking for change management
- Extensible custom fields system

## Migration Notes

- All tables use `IF NOT EXISTS` for safe installation
- Default data is inserted with conflict handling
- Schema is backward compatible with existing system
- No modifications required to existing tables
