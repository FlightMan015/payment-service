-- This file holds all of the database records which have application logic attached to them and are defined by the system
-- These records are needed for the application to function properly and should not be modified or deleted unless the associated application code has been updated as well
-- Any DB records defined here should follow these rules:
    -- 1. The record IDs (Primary Keys) should be explicitly set here so the application can depend on it
    -- 2. The records should be inserted using an on duplicate key update style operation to ensure that the records are not duplicated and that we can safely execute this file multiple times

-- Authorization Schema

-- Actions
INSERT INTO auth.actions (id, name) OVERRIDING SYSTEM VALUE
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

-- Ensure that the primary key sequence is set to the max id
SELECT setval(pg_get_serial_sequence('auth.actions', 'id'), (SELECT MAX(id) FROM auth.actions) + 1);

-- Resources
INSERT INTO auth.resources (id, name) OVERRIDING SYSTEM VALUE
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
    (12, 'subscription'),
    (13, 'application'),
    (14, 'payment_options'),
    (15, 'payment_method')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;

-- Ensure that the primary key sequence is set to the max id
SELECT setval(pg_get_serial_sequence('auth.resources', 'id'), (SELECT MAX(id) FROM auth.resources) + 1);

-- Services
INSERT INTO auth.services (id, name) OVERRIDING SYSTEM VALUE
VALUES
    (1, '*'),
    (2, 'cleo_crm'),
    (3, 'fsa_backend')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;

-- Ensure that the primary key sequence is set to the max id
SELECT setval(pg_get_serial_sequence('auth.services', 'id'), (SELECT MAX(id) FROM auth.services) + 1);

-- Fields
INSERT INTO auth.fields (id, name) OVERRIDING SYSTEM VALUE
VALUES
    (1, '*')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;

-- Ensure that the primary key sequence is set to the max id
SELECT setval(pg_get_serial_sequence('auth.fields', 'id'), (SELECT MAX(id) FROM auth.fields) + 1);

-- Permissions
INSERT INTO auth.permissions (id, service_id, resource_id, field_id, action_id) OVERRIDING SYSTEM VALUE
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
    (82, 2, 12, 1, 3), -- cleo_crm:subscription:*:edit
    (83, 2, 13, 1, 1), -- cleo_crm:application:*:browse
    (84, 2, 13, 1, 2), -- cleo_crm:application:*:read
    (85, 2, 13, 1, 3), -- cleo_crm:application:*:edit
    (86, 2, 13, 1, 4), -- cleo_crm:application:*:add
    (87, 2, 13, 1, 5), -- cleo_crm:application:*:delete
    (88, 2, 13, 1, 6), -- cleo_crm:application:*:login
    (89, 2, 13, 1, 7), -- cleo_crm:application:*:*
    (90, 2, 14, 1, 1), -- cleo_crm:payment_options:*:browse
    (91, 2, 14, 1, 2), -- cleo_crm:payment_options:*:read
    (92, 2, 14, 1, 3), -- cleo_crm:payment_options:*:edit
    (93, 2, 14, 1, 4), -- cleo_crm:payment_options:*:add
    (94, 2, 14, 1, 5), -- cleo_crm:payment_options:*:delete
    (95, 2, 14, 1, 6), -- cleo_crm:payment_options:*:login
    (96, 2, 14, 1, 7), -- cleo_crm:payment_options:*:*
    (97, 2, 15, 1, 1), -- cleo_crm:payment_method:*:browse
    (98, 2, 15, 1, 2), -- cleo_crm:payment_method:*:read
    (99, 2, 15, 1, 3), -- cleo_crm:payment_method:*:edit
    (100, 2, 15, 1, 4), -- cleo_crm:payment_method:*:add
    (101, 2, 15, 1, 5), -- cleo_crm:payment_method:*:delete
    (102, 2, 15, 1, 6), -- cleo_crm:payment_method:*:login
    (103, 2, 15, 1, 7) -- cleo_crm:payment_method:*:*
ON CONFLICT (id)
DO UPDATE SET
    service_id = EXCLUDED.service_id,
    resource_id = EXCLUDED.resource_id,
    field_id = EXCLUDED.field_id,
    action_id = EXCLUDED.action_id;

-- Ensure that the primary key sequence is set to the max id
SELECT setval(pg_get_serial_sequence('auth.permissions', 'id'), (SELECT MAX(id) FROM auth.permissions) + 1);

-- Roles
-- Ensure we always have at least one role of Super Administrator with all permissions. No other roles should be defined here as they are defined by users
INSERT INTO auth.roles (id, name) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Super Administrator')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;

-- Ensure that the primary key sequence is set to the max id
SELECT setval(pg_get_serial_sequence('auth.roles', 'id'), (SELECT MAX(id) FROM auth.roles) + 1);

-- Role Permissions
-- Ensure our one Super Admin role has all permissions
INSERT INTO auth.role_permissions (role_id, permission_id)
    (SELECT 1, id FROM auth.permissions)
ON CONFLICT (role_id, permission_id)
DO NOTHING;

-- IDP Roles
-- Ensure we have at least one IDP group mapping for our Super Admin role
INSERT INTO auth.idp_roles (id, role_id, idp_group) OVERRIDING SYSTEM VALUE VALUES
    (1, 1, 'AppRole-Aptive CRM-Administrator') -- The name of the idp group is determined by the idp in use
ON CONFLICT (id)
DO UPDATE SET
    role_id = EXCLUDED.role_id,
    idp_group = EXCLUDED.idp_group;

-- Ensure that the primary key sequence is set to the max id
SELECT setval(pg_get_serial_sequence('auth.idp_roles', 'id'), (SELECT MAX(id) FROM auth.idp_roles) + 1);

-- Field Operations Schema

-- Appointment Statuses
INSERT INTO field_operations.appointment_statuses (id, name, external_ref_id) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Pending', 0),
    (2, 'Completed', 1),
    (3, 'Rescheduled', -2),
    (4, 'No Show', 2),
    (5, 'Cancelled', -1)
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name,
    external_ref_id = EXCLUDED.external_ref_id;

-- Ensure that the primary key sequence is set to the max id
SELECT setval(pg_get_serial_sequence('field_operations.appointment_statuses', 'id'), (SELECT MAX(id) FROM field_operations.appointment_statuses) + 1);

-- Appointment Types
INSERT INTO field_operations.appointment_types (id, name) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Recurring'),
    (2, 'Initial'),
    (3, 'Reservice'),
    (4, 'Quality Assurance')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;

-- Ensure that the primary key sequence is set to the max id
SELECT setval(pg_get_serial_sequence('field_operations.appointment_types', 'id'), (SELECT MAX(id) FROM field_operations.appointment_types) + 1);

-- Billing Schema

-- Payment Statuses
INSERT INTO billing.payment_statuses (id, name, external_ref_id, description) OVERRIDING SYSTEM VALUE VALUES
    (1, 'AuthCapturing', 1, NULL),
    (2, 'Captured', 1, NULL),
    (3, 'Authorizing', 1, NULL),
    (4, 'Authorized', 1, NULL),
    (5, 'Capturing', 1, NULL),
    (6, 'Cancelling', 1, NULL),
    (7, 'Cancelled', 1, NULL),
    (8, 'Crediting', 2, NULL),
    (9, 'Credited', 2, NULL),
    (10, 'Declined', 0, NULL),
    ( 11, 'Suspended', NULL, 'A payment is suspended and cannot be processed via gateway'),
	( 12, 'Terminated', NULL, 'If the payment is Suspended, it can be Terminated to fully skip the payment'),
	( 13, 'Processed', NULL, 'If the payment is Suspended, it can be changed to Processed to keep processing this payment'),
	( 14, 'Returned', NULL, 'Relate to Worldpay - ACH payment status Returned'),
	( 15, 'Settled', NULL, 'Relate to Worldpay - ACH payment status Settled')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name,
    external_ref_id = EXCLUDED.external_ref_id;

-- Ensure that the primary key sequence is set to the max id
SELECT setval(pg_get_serial_sequence('billing.payment_statuses', 'id'), (SELECT MAX(id) FROM billing.payment_statuses) + 1);

-- Payment Types
INSERT INTO billing.payment_types (id, name, description, external_ref_id, sort_order) OVERRIDING SYSTEM VALUE VALUES
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

-- Ensure that the primary key sequence is set to the max id
SELECT setval(pg_get_serial_sequence('billing.payment_types', 'id'), (SELECT MAX(id) FROM billing.payment_types) + 1);

-- Payment Gateways
INSERT INTO billing.payment_gateways (id, name, description, is_hidden, is_enabled) OVERRIDING SYSTEM VALUE VALUES
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

-- Ensure that the primary key sequence is set to the max id
SELECT setval(pg_get_serial_sequence('billing.payment_gateways', 'id'), (SELECT MAX(id) FROM billing.payment_gateways) + 1);

-- Transaction Types
INSERT INTO billing.transaction_types (id, name, description) OVERRIDING SYSTEM VALUE VALUES
    (1, 'AuthCapture', 'Authorize and Capture'),
    (2, 'Authorize', 'Authorize'),
    (3, 'Capture', 'Capture previously authorized transaction'),
    (4, 'Cancel', 'Cancel / Void'),
    (5, 'Check Status', 'Get the current status of a payment'),
    (6, 'Credit', 'Send funds back to payor'),
    (7, 'Tokenize', 'Create a payment token'),
    (8, 'Return', 'Check if the ACH payment was returned/settled')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description;

-- Ensure that the primary key sequence is set to the max id
SELECT setval(pg_get_serial_sequence('billing.transaction_types', 'id'), (SELECT MAX(id) FROM billing.transaction_types) + 1);

-- Payment Suspended Reasons
INSERT INTO billing.suspend_reasons (id, name, description) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Duplicate', 'Duplicated with another payment')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description;

-- Ensure that the primary key sequence is set to the max id
SELECT setval(pg_get_serial_sequence('billing.suspend_reasons', 'id'), (SELECT MAX(id) FROM billing.suspend_reasons) + 1);

-- Scheduled Payment Statuses
INSERT INTO billing.scheduled_payment_statuses (id, name, description) OVERRIDING SYSTEM VALUE
VALUES (1, 'Pending', 'Payment is waiting to be processed by the trigger event'),
       (2, 'Cancelled', 'Processing of the scheduled payment was cancelled'),
       (3, 'Submitted', 'Payment was submitted to the payment processor')
ON CONFLICT (id)
DO UPDATE SET
  name        = EXCLUDED.name,
  description = EXCLUDED.description;

-- Ensure that the primary key sequence is set to the max id
SELECT SETVAL(PG_GET_SERIAL_SEQUENCE('billing.scheduled_payment_statuses', 'id'),
              (SELECT MAX(id) FROM billing.scheduled_payment_statuses) + 1);

-- Scheduled Payment Triggers
INSERT INTO billing.scheduled_payment_triggers (id, name, description) OVERRIDING SYSTEM VALUE
VALUES (1, 'Initial Service Completed', 'One-time payment for the completion of the initial service')
ON CONFLICT (id)
DO UPDATE SET
  name        = EXCLUDED.name,
  description = EXCLUDED.description;

-- Ensure that the primary key sequence is set to the max id
SELECT SETVAL(PG_GET_SERIAL_SEQUENCE('billing.scheduled_payment_triggers', 'id'), (SELECT MAX(id) FROM billing.scheduled_payment_triggers) + 1);

-- Decline Reasons
INSERT INTO billing.decline_reasons (id, name, description, is_reprocessable) OVERRIDING SYSTEM VALUE
VALUES (1, 'Declined', 'Generic Decline', true),
       (2, 'Expired', 'The card is expired', false),
       (3, 'Duplicate', 'The transaction has been duplicated within a specified time window', true),
       (4, 'Invalid', 'There is an issue with the card itself', false),
       (5, 'Fraud', 'The card has been flagged for fraudulent activities', false),
       (6, 'Insufficient Funds', 'The card does not have sufficient funds for the requested payment', true),
       (7, 'Error', 'Network communication error', true),
       (8, 'Contact Financial Institution', 'There is an issue with the card and the financial institution should be contacted by the card bearer', false)
ON CONFLICT (id)
DO UPDATE SET
  name            = EXCLUDED.name,
  description     = EXCLUDED.description,
  is_reprocessable = EXCLUDED.is_reprocessable;

-- Ensure that the primary key sequence is set to the max id
SELECT SETVAL(PG_GET_SERIAL_SEQUENCE('billing.decline_reasons', 'id'), (SELECT MAX(id) FROM billing.decline_reasons) + 1);

-- Dealers
INSERT INTO organization.dealers (id, name) OVERRIDING SYSTEM VALUE
VALUES(1, 'Generic Dealer')
ON CONFLICT (id)
DO UPDATE SET
  name            = EXCLUDED.name;
-- Ensure that the primary key sequence is set to the max id
SELECT SETVAL(PG_GET_SERIAL_SEQUENCE('organization.dealers', 'id'), (SELECT MAX(id) FROM organization.dealers) + 1);
