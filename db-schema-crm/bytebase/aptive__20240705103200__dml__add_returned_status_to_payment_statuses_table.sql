INSERT INTO billing.payment_statuses (id, name, description)
    OVERRIDING SYSTEM VALUE
VALUES (14, 'Returned', 'A payment was returned and was not fully processed in the gateway (for example it was NSF)');
