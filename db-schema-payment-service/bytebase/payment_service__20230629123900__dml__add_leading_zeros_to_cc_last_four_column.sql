UPDATE payment_methods
SET cc_last_four=LPAD(cc_last_four, 4, 0);