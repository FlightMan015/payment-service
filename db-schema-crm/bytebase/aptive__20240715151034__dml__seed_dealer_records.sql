-- Dealers
INSERT INTO organization.dealers (id, name) OVERRIDING SYSTEM VALUE
VALUES
    (1, 'Generic Dealer')
ON CONFLICT (id)
DO UPDATE SET
    name = EXCLUDED.name;
