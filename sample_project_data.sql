-- Sample Data for Project Management System
-- Run this after installing the main schema

-- Sample projects
INSERT INTO projects (project_name, project_key, description, project_manager_id, status, start_date, end_date, budget, created_by) VALUES
('ERP System Enhancement', 'ERP', 'Improve the existing ERP system with new features', 1, 'active', CURRENT_DATE, CURRENT_DATE + INTERVAL '6 months', 50000.00, 1),
('Customer Portal', 'PORTAL', 'Build a customer self-service portal', 1, 'active', CURRENT_DATE, CURRENT_DATE + INTERVAL '4 months', 30000.00, 1),
('Mobile App Development', 'MOBILE', 'Develop mobile application for field workers', 1, 'planned', CURRENT_DATE + INTERVAL '1 month', CURRENT_DATE + INTERVAL '8 months', 75000.00, 1);

-- Sample project statuses for each project
-- ERP Project statuses
INSERT INTO issue_statuses (name, status_type, project_id, position, color) VALUES
('Backlog', 'todo', 1, 1, '#6c757d'),
('To Do', 'todo', 1, 2, '#007bff'),
('In Progress', 'in_progress', 1, 3, '#ffc107'),
('Code Review', 'review', 1, 4, '#17a2b8'),
('Testing', 'review', 1, 5, '#6f42c1'),
('Done', 'done', 1, 6, '#28a745');

-- Portal Project statuses
INSERT INTO issue_statuses (name, status_type, project_id, position, color) VALUES
('New', 'todo', 2, 1, '#6c757d'),
('Development', 'in_progress', 2, 2, '#ffc107'),
('Review', 'review', 2, 3, '#17a2b8'),
('Completed', 'done', 2, 4, '#28a745');

-- Mobile Project statuses
INSERT INTO issue_statuses (name, status_type, project_id, position, color) VALUES
('Planned', 'todo', 3, 1, '#6c757d'),
('Design', 'in_progress', 3, 2, '#e83e8c'),
('Development', 'in_progress', 3, 3, '#ffc107'),
('Testing', 'review', 3, 4, '#6f42c1'),
('Deployed', 'done', 3, 5, '#28a745');

-- Sample teams
INSERT INTO teams (name, description, lead_id, created_by) VALUES
('Backend Development', 'Server-side development team', 1, 1),
('Frontend Development', 'User interface development team', 1, 1),
('QA Testing', 'Quality assurance and testing team', 1, 1),
('DevOps', 'Development operations and infrastructure', 1, 1);

-- Sample labels for projects
INSERT INTO project_labels (name, color, project_id) VALUES
-- ERP Project labels
('backend', '#007bff', 1),
('frontend', '#28a745', 1),
('database', '#6f42c1', 1),
('api', '#fd7e14', 1),
('security', '#dc3545', 1),
-- Portal Project labels
('authentication', '#007bff', 2),
('ui-ux', '#28a745', 2),
('integration', '#6f42c1', 2),
-- Mobile Project labels
('android', '#28a745', 3),
('ios', '#007bff', 3),
('cross-platform', '#6f42c1', 3);

-- Sample sprints
INSERT INTO sprints (project_id, name, goal, start_date, end_date, status, created_by) VALUES
(1, 'ERP Sprint 1', 'Implement user management improvements', CURRENT_DATE - INTERVAL '2 weeks', CURRENT_DATE, 'completed', 1),
(1, 'ERP Sprint 2', 'Add reporting features', CURRENT_DATE, CURRENT_DATE + INTERVAL '2 weeks', 'active', 1),
(2, 'Portal Sprint 1', 'Set up authentication system', CURRENT_DATE - INTERVAL '1 week', CURRENT_DATE + INTERVAL '1 week', 'active', 1);

-- Sample issues
INSERT INTO issues (issue_key, project_id, title, description, issue_type_id, status_id, priority_id, assignee_id, reporter_id, story_points, original_estimate, created_by) VALUES
-- ERP Project issues
(generate_issue_key('ERP'), 1, 'Add two-factor authentication', 'Implement 2FA for enhanced security', 2, 6, 2, NULL, 1, 8, 480, 1),
(generate_issue_key('ERP'), 1, 'Fix login timeout issue', 'Users are being logged out too frequently', 1, 5, 1, NULL, 1, 3, 120, 1),
(generate_issue_key('ERP'), 1, 'Improve dashboard performance', 'Dashboard loads slowly with large datasets', 2, 3, 2, NULL, 1, 5, 240, 1),
(generate_issue_key('ERP'), 1, 'Add user role management', 'Ability to assign and manage user roles', 2, 2, 3, NULL, 1, 13, 720, 1),

-- Portal Project issues  
(generate_issue_key('PORTAL'), 2, 'Design login page', 'Create responsive login interface', 4, 4, 3, NULL, 1, 5, 300, 1),
(generate_issue_key('PORTAL'), 2, 'Set up user registration', 'Allow customers to create new accounts', 2, 2, 2, NULL, 1, 8, 480, 1),
(generate_issue_key('PORTAL'), 2, 'Customer profile management', 'Customers can view and edit their profiles', 2, 1, 3, NULL, 1, 13, 600, 1),

-- Mobile Project issues
(generate_issue_key('MOBILE'), 3, 'Mobile app architecture', 'Define the overall architecture for the mobile app', 3, 1, 2, NULL, 1, 21, 960, 1),
(generate_issue_key('MOBILE'), 3, 'Research cross-platform frameworks', 'Evaluate React Native vs Flutter vs Native', 3, 1, 3, NULL, 1, 8, 480, 1);

-- Sample issue comments
INSERT INTO issue_comments (issue_id, author_id, content) VALUES
(1, 1, 'This should integrate with our existing SMS system for sending codes.'),
(2, 1, 'This is affecting multiple users. High priority fix needed.'),
(3, 1, 'Consider implementing pagination and lazy loading for better performance.'),
(5, 1, 'Make sure the design follows our brand guidelines and is mobile-responsive.');

-- Sample work logs
INSERT INTO work_logs (issue_id, user_id, time_spent, work_date, description) VALUES
(1, 1, 120, CURRENT_DATE - INTERVAL '1 day', 'Research 2FA implementation options'),
(1, 1, 180, CURRENT_DATE, 'Started implementing SMS integration'),
(2, 1, 60, CURRENT_DATE - INTERVAL '2 days', 'Investigated timeout configuration'),
(2, 1, 90, CURRENT_DATE - INTERVAL '1 day', 'Fixed session timeout issue'),
(3, 1, 240, CURRENT_DATE, 'Performance analysis and optimization planning');

-- Sample issue activity (this would normally be generated automatically)
INSERT INTO issue_activity (issue_id, user_id, action, field_name, old_value, new_value) VALUES
(1, 1, 'created', NULL, NULL, NULL),
(1, 1, 'updated', 'status', 'To Do', 'In Progress'),
(2, 1, 'created', NULL, NULL, NULL),
(2, 1, 'updated', 'status', 'To Do', 'Testing'),
(3, 1, 'created', NULL, NULL, NULL);

-- Sample notifications
INSERT INTO project_notifications (user_id, type, title, message, related_issue_id, related_project_id) VALUES
(1, 'issue_assigned', 'New issue assigned', 'You have been assigned to work on ERP-3', 3, 1),
(1, 'issue_updated', 'Issue updated', 'ERP-2 has been moved to Testing', 2, 1),
(1, 'comment_added', 'New comment', 'A new comment was added to ERP-1', 1, 1);

-- Sample custom fields
INSERT INTO custom_fields (name, field_type, applies_to, project_id) VALUES
('Customer Impact', 'select', 'issue', 1),
('Browser Compatibility', 'multi_select', 'issue', 2),
('Device Type', 'select', 'issue', 3);

-- Sample project members (assuming you have users with IDs 1, 2, 3 in your brugere table)
-- You'll need to adjust these based on your actual user IDs
INSERT INTO project_members (project_id, user_id, role) VALUES
(1, 1, 'admin'),
(2, 1, 'admin'),
(3, 1, 'admin');

-- Sample team members
INSERT INTO team_members (team_id, user_id, role) VALUES
(1, 1, 'lead'),
(2, 1, 'member'),
(3, 1, 'member');

-- Sample project-team assignments
INSERT INTO project_teams (project_id, team_id) VALUES
(1, 1), -- ERP project uses Backend team
(1, 2), -- ERP project uses Frontend team
(2, 2), -- Portal project uses Frontend team
(3, 1), -- Mobile project uses Backend team
(3, 2); -- Mobile project uses Frontend team

-- Sample boards
INSERT INTO boards (project_id, name, type, created_by) VALUES
(1, 'ERP Kanban Board', 'kanban', 1),
(2, 'Portal Scrum Board', 'scrum', 1),
(3, 'Mobile Development Board', 'kanban', 1);

-- Sample sprint-issue assignments
INSERT INTO sprint_issues (sprint_id, issue_id) VALUES
(1, 1), -- ERP-1 was in Sprint 1
(1, 2), -- ERP-2 was in Sprint 1  
(2, 3), -- ERP-3 is in Sprint 2
(2, 4), -- ERP-4 is in Sprint 2
(3, 5); -- PORTAL-1 is in Portal Sprint 1

-- Sample issue labels assignments
INSERT INTO issue_project_labels (issue_id, label_id) VALUES
(1, 5), -- ERP-1 has 'security' label
(2, 1), -- ERP-2 has 'backend' label
(3, 2), -- ERP-3 has 'frontend' label
(3, 3), -- ERP-3 also has 'database' label
(5, 8), -- PORTAL-1 has 'ui-ux' label
(6, 7); -- PORTAL-2 has 'authentication' label

-- Sample watchers
INSERT INTO issue_watchers (issue_id, user_id) VALUES
(1, 1), -- User 1 watches ERP-1
(2, 1), -- User 1 watches ERP-2
(3, 1); -- User 1 watches ERP-3

COMMIT;
