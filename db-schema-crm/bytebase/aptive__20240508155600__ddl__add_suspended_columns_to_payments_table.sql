ALTER TABLE
    "billing"."payments"
ADD
    COLUMN "suspended_at" timestamptz; 

ALTER TABLE
    "billing"."payments"
ADD
    COLUMN "suspend_reason_id" int4,
ADD
    CONSTRAINT "fk_payments_suspend_reason_id_suspend_reasons_id" FOREIGN KEY ("suspend_reason_id") REFERENCES "billing"."suspend_reasons" ("id");