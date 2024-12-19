ALTER TABLE customer_payments
    CHANGE notification_sent_at notification_sent_at TIMESTAMP(6) DEFAULT NULL;