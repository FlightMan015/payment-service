-- Resources
INSERT INTO auth.resources (id, name) OVERRIDING SYSTEM VALUE
VALUES
    (14, 'payment_options')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;

-- Permissions
INSERT INTO auth.permissions (id, service_id, resource_id, field_id, action_id) OVERRIDING SYSTEM VALUE
VALUES
    (90, 2, 14, 1, 1), -- cleo_crm:payment_options:*:browse
    (91, 2, 14, 1, 2), -- cleo_crm:payment_options:*:read
    (92, 2, 14, 1, 3), -- cleo_crm:payment_options:*:edit
    (93, 2, 14, 1, 4), -- cleo_crm:payment_options:*:add
    (94, 2, 14, 1, 5), -- cleo_crm:payment_options:*:delete
    (95, 2, 14, 1, 6), -- cleo_crm:payment_options:*:login
    (96, 2, 14, 1, 7) -- cleo_crm:payment_options:*:*
ON CONFLICT (id)
DO UPDATE SET
    service_id = EXCLUDED.service_id,
    resource_id = EXCLUDED.resource_id,
    field_id = EXCLUDED.field_id,
    action_id = EXCLUDED.action_id;
