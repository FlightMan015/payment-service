-- Resources
INSERT INTO auth.resources (id, name) OVERRIDING SYSTEM VALUE
VALUES
    (15, 'payment_method')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;

-- Permissions
INSERT INTO auth.permissions (id, service_id, resource_id, field_id, action_id) OVERRIDING SYSTEM VALUE
VALUES
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
