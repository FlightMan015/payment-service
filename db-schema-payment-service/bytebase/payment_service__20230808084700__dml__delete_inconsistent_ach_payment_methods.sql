SELECT @ACH_PAYMENT_TYPE := id
FROM payment_types
WHERE payment_types.name = 'ACH';

DELETE
FROM payment_methods
WHERE payment_methods.payment_type_id = @ACH_PAYMENT_TYPE
  AND (payment_methods.ach_account_number_encrypted IS NULL OR payment_methods.ach_routing_number IS NULL)