-- Resources
INSERT INTO auth.resources (id, name) OVERRIDING SYSTEM VALUE
VALUES
    (13, 'application')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;

-- Permissions
INSERT INTO auth.permissions (id, service_id, resource_id, field_id, action_id) OVERRIDING SYSTEM VALUE
VALUES
    (83, 2, 13, 1, 1), -- cleo_crm:application:*:browse
    (84, 2, 13, 1, 2), -- cleo_crm:application:*:read
    (85, 2, 13, 1, 3), -- cleo_crm:application:*:edit
    (86, 2, 13, 1, 4), -- cleo_crm:application:*:add
    (87, 2, 13, 1, 5), -- cleo_crm:application:*:delete
    (88, 2, 13, 1, 6), -- cleo_crm:application:*:login
    (89, 2, 13, 1, 7) -- cleo_crm:application:*:*
ON CONFLICT (id)
DO UPDATE SET
    service_id = EXCLUDED.service_id,
    resource_id = EXCLUDED.resource_id,
    field_id = EXCLUDED.field_id,
    action_id = EXCLUDED.action_id;
