-- This file holds all of the database records which have application logic attached to them and are defined by the system, we are explicitly setting the primary keys so that we can depend on them in the application code

-- Start by deleting all records in role_permissions as we are modifying the permissions primary key below to be in ascending incremental order without gaps and explicitly setting the primary key values
DELETE FROM auth.role_permissions;

-- Authorization Schema

-- Actions
INSERT INTO auth.actions (id, name)
VALUES 
    (1, 'browse'),
    (2, 'read'),
    (3, 'edit'),
    (4, 'add'),
    (5, 'delete'),
    (6, 'login'),
    (7, '*')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;

-- Resources
INSERT INTO auth.resources (id, name)
VALUES 
    (1, '*'),
    (2, 'dealer'),
    (3, 'account'),
    (4, 'note'),
    (5, 'role'),
    (6, 'payment'),
    (7, 'user'),
    (8, 'permission'),
    (9, 'invoice'),
    (10, 'api_account'),
    (11, 'collection'),
    (12, 'subscription')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;

-- Services
INSERT INTO auth.services (id, name)
VALUES 
    (1, '*'),
    (2, 'cleo_crm'),
    (3, 'fsa_backend')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;

-- Fields
INSERT INTO auth.fields (id, name)
VALUES 
    (1, '*')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;

-- Permissions
INSERT INTO auth.permissions (id, service_id, resource_id, field_id, action_id)
VALUES 
    (1, 2, 1, 1, 1), -- cleo_crm:*:*:browse
    (2, 2, 1, 1, 2), -- cleo_crm:*:*:read
    (3, 2, 1, 1, 3), -- cleo_crm:*:*:edit
    (4, 2, 1, 1, 4), -- cleo_crm:*:*:add
    (5, 2, 1, 1, 5), -- cleo_crm:*:*:delete
    (6, 2, 1, 1, 6), -- cleo_crm:*:*:login
    (7, 2, 1, 1, 7), -- cleo_crm:*:*:*
    (8, 2, 2, 1, 1), -- cleo_crm:dealer:*:browse
    (9, 2, 2, 1, 2), -- cleo_crm:dealer:*:read
    (10, 2, 2, 1, 3), -- cleo_crm:dealer:*:edit
    (11, 2, 2, 1, 4), -- cleo_crm:dealer:*:add
    (12, 2, 2, 1, 5), -- cleo_crm:dealer:*:delete
    (13, 2, 2, 1, 6), -- cleo_crm:dealer:*:login
    (14, 2, 2, 1, 7), -- cleo_crm:dealer:*:*
    (15, 2, 3, 1, 1), -- cleo_crm:account:*:browse
    (16, 2, 3, 1, 2), -- cleo_crm:account:*:read
    (17, 2, 3, 1, 3), -- cleo_crm:account:*:edit
    (18, 2, 3, 1, 4), -- cleo_crm:account:*:add
    (19, 2, 3, 1, 5), -- cleo_crm:account:*:delete
    (20, 2, 3, 1, 6), -- cleo_crm:account:*:login
    (21, 2, 3, 1, 7), -- cleo_crm:account:*:*
    (22, 2, 4, 1, 1), -- cleo_crm:note:*:browse
    (23, 2, 4, 1, 2), -- cleo_crm:note:*:read
    (24, 2, 4, 1, 3), -- cleo_crm:note:*:edit
    (25, 2, 4, 1, 4), -- cleo_crm:note:*:add
    (26, 2, 4, 1, 5), -- cleo_crm:note:*:delete
    (27, 2, 4, 1, 6), -- cleo_crm:note:*:login
    (28, 2, 4, 1, 7), -- cleo_crm:note:*:*
    (29, 2, 5, 1, 1), -- cleo_crm:role:*:browse
    (30, 2, 5, 1, 2), -- cleo_crm:role:*:read
    (31, 2, 5, 1, 3), -- cleo_crm:role:*:edit
    (32, 2, 5, 1, 4), -- cleo_crm:role:*:add
    (33, 2, 5, 1, 5), -- cleo_crm:role:*:delete
    (34, 2, 5, 1, 6), -- cleo_crm:role:*:login
    (35, 2, 5, 1, 7), -- cleo_crm:role:*:*
    (36, 2, 6, 1, 1), -- cleo_crm:payment:*:browse
    (37, 2, 6, 1, 2), -- cleo_crm:payment:*:read
    (38, 2, 6, 1, 3), -- cleo_crm:payment:*:edit
    (39, 2, 6, 1, 4), -- cleo_crm:payment:*:add
    (40, 2, 6, 1, 5), -- cleo_crm:payment:*:delete
    (41, 2, 6, 1, 6), -- cleo_crm:payment:*:login
    (42, 2, 6, 1, 7), -- cleo_crm:payment:*:*
    (43, 2, 7, 1, 1), -- cleo_crm:user:*:browse
    (44, 2, 7, 1, 2), -- cleo_crm:user:*:read
    (45, 2, 7, 1, 3), -- cleo_crm:user:*:edit
    (46, 2, 7, 1, 4), -- cleo_crm:user:*:add
    (47, 2, 7, 1, 5), -- cleo_crm:user:*:delete
    (48, 2, 7, 1, 6), -- cleo_crm:user:*:login
    (49, 2, 7, 1, 7), -- cleo_crm:user:*:*
    (50, 2, 8, 1, 1), -- cleo_crm:permission:*:browse
    (51, 2, 8, 1, 2), -- cleo_crm:permission:*:read
    (52, 2, 8, 1, 3), -- cleo_crm:permission:*:edit
    (53, 2, 8, 1, 4), -- cleo_crm:permission:*:add
    (54, 2, 8, 1, 5), -- cleo_crm:permission:*:delete
    (55, 2, 8, 1, 6), -- cleo_crm:permission:*:login
    (56, 2, 8, 1, 7), -- cleo_crm:permission:*:*
    (57, 2, 9, 1, 1), -- cleo_crm:invoice:*:browse
    (58, 2, 9, 1, 2), -- cleo_crm:invoice:*:read
    (59, 2, 9, 1, 3), -- cleo_crm:invoice:*:edit
    (60, 2, 9, 1, 4), -- cleo_crm:invoice:*:add
    (61, 2, 9, 1, 5), -- cleo_crm:invoice:*:delete
    (62, 2, 9, 1, 6), -- cleo_crm:invoice:*:login
    (63, 2, 9, 1, 7), -- cleo_crm:invoice:*:*
    (64, 3, 1, 1, 1), -- fsa_backend:*:*:browse
    (65, 3, 1, 1, 2), -- fsa_backend:*:*:read
    (66, 3, 1, 1, 3), -- fsa_backend:*:*:edit
    (67, 3, 1, 1, 4), -- fsa_backend:*:*:add
    (68, 3, 1, 1, 5), -- fsa_backend:*:*:delete
    (69, 3, 1, 1, 6), -- fsa_backend:*:*:login
    (70, 3, 1, 1, 7), -- fsa_backend:*:*:*
    (71, 1, 10, 1, 1), -- *:api_account:*:browse
    (72, 1, 10, 1, 2), -- *:api_account:*:read
    (73, 1, 10, 1, 3), -- *:api_account:*:edit
    (74, 1, 10, 1, 4), -- *:api_account:*:add
    (75, 1, 10, 1, 5), -- *:api_account:*:delete
    (76, 2, 10, 1, 1), -- cleo_crm:api_account:*:browse
    (77, 2, 10, 1, 2), -- cleo_crm:api_account:*:read
    (78, 2, 10, 1, 3), -- cleo_crm:api_account:*:edit
    (79, 2, 10, 1, 4), -- cleo_crm:api_account:*:add
    (80, 2, 10, 1, 5), -- cleo_crm:api_account:*:delete
    (81, 2, 11, 1, 3), -- cleo_crm:collection:*:edit
    (82, 2, 12, 1, 3) -- cleo_crm:subscription:*:edit
ON CONFLICT (id)
DO UPDATE SET
    service_id = EXCLUDED.service_id,
    resource_id = EXCLUDED.resource_id,
    field_id = EXCLUDED.field_id,
    action_id = EXCLUDED.action_id;

-- Roles
-- Ensure we always have at least one role of Super Administrator with all permissions. No other roles should be defined here as they are defined by users
INSERT INTO auth.roles (id, name) VALUES 
    (1, 'Super Administrator')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;

-- Role Permissions
-- Ensure our one Super Admin role has all permissions
INSERT INTO auth.role_permissions (role_id, permission_id)
    (SELECT 1, id FROM auth.permissions)
ON CONFLICT (role_id, permission_id)
DO NOTHING;

-- IDP Roles
-- Ensure we have at least one IDP group mapping for our Super Admin role
INSERT INTO auth.idp_roles (id, role_id, idp_group) VALUES
    (1, 1, 'AppRole-FusionAuth-CRM-Users') -- The name of the idp group is determined by the idp in use
ON CONFLICT (id)
DO UPDATE SET
    role_id = EXCLUDED.role_id,
    idp_group = EXCLUDED.idp_group;

-- Field Operations Schema

-- Appointment Statuses
INSERT INTO field_operations.appointment_statuses (id, name, external_ref_id) VALUES
    (1, 'Pending', 0),
    (2, 'Completed', 1),
    (3, 'Rescheduled', -2),
    (4, 'No Show', 2),
    (5, 'Cancelled', -1)
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name,
    external_ref_id = EXCLUDED.external_ref_id;

-- Appointment Types
INSERT INTO field_operations.appointment_types (id, name) VALUES
    (1, 'Recurring'),
    (2, 'Initial'),
    (3, 'Reservice'),
    (4, 'Quality Assurance')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;

-- Billing Schema

-- Payment Statuses
INSERT INTO billing.payment_statuses (id, name, external_ref_id) VALUES
    (1, 'AuthCapturing', 1),
    (2, 'Captured', 1),
    (3, 'Authorizing', 1),
    (4, 'Authorized', 1),
    (5, 'Capturing', 1),
    (6, 'Cancelling', 1),
    (7, 'Cancelled', 1),
    (8, 'Crediting', 2),
    (9, 'Credited', 2),
    (10, 'Declined', 0)
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name,
    external_ref_id = EXCLUDED.external_ref_id;

-- Payment Types
INSERT INTO billing.payment_types (id, name, description, external_ref_id, sort_order) VALUES
    (3, 'CASH', NULL, 1, 1),
    (1, 'CC', NULL, 3, 3),
    (4, 'CHECK', NULL, 2, 2),
    (5, 'COUPON', NULL, 0, 6),
    (6, 'CREDIT_MEMO', NULL, 5, 5),
    (2, 'ACH', NULL, 4, 4)
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description,
    external_ref_id = EXCLUDED.external_ref_id;

-- Payment Gateways
INSERT INTO billing.payment_gateways (id, name, description, is_hidden, is_enabled) VALUES
    (1, 'Worldpay', 'The payment gateway for Element/Vantive/Worldpay', false, true),
    (2, 'Worldpay Tokenex Transparent', 'The payment gateway for Worldpay Tokenex Transparent', false, true),
    (3, 'Check', 'Check', false, true),
    (4, 'Credit', 'The pseudo payment gateway for Credit Payment', false, true)
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description,
    is_hidden = EXCLUDED.is_hidden,
    is_enabled = EXCLUDED.is_enabled;

-- Transaction Types
INSERT INTO billing.transaction_types (id, name, description) VALUES
    (1, 'AuthCapture', 'Authorize and Capture'),
    (2, 'Authorize', 'Authorize'),
    (3, 'Capture', 'Capture previously authorized transaction'),
    (4, 'Cancel', 'Cancel / Void'),
    (5, 'Check Status', 'Get the current status of a payment'),
    (6, 'Credit', 'Send funds back to payor'),
    (7, 'Tokenize', 'Create a payment token')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description;

