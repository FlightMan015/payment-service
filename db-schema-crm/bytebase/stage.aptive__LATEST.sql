
SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

CREATE SCHEMA alterra_production;

CREATE SCHEMA audit;

CREATE SCHEMA auth;

CREATE EXTENSION IF NOT EXISTS aws_commons WITH SCHEMA public;

CREATE EXTENSION IF NOT EXISTS aws_lambda WITH SCHEMA public;

CREATE EXTENSION IF NOT EXISTS aws_s3 WITH SCHEMA public;

CREATE SCHEMA billing;

CREATE SCHEMA crm;

CREATE SCHEMA customer;

CREATE SCHEMA datadog;

CREATE SCHEMA dbe_test;

CREATE SCHEMA field_operations;

CREATE SCHEMA guru;

CREATE SCHEMA licensing;

CREATE SCHEMA notifications;

CREATE SCHEMA organization;

CREATE SCHEMA pestroutes;

CREATE SCHEMA product;

CREATE SCHEMA sales;

CREATE SCHEMA spt;

CREATE SCHEMA spt_old;

CREATE SCHEMA street_smarts;

CREATE SCHEMA street_smarts_old;

CREATE SCHEMA tiger;

CREATE SCHEMA tiger_data;

CREATE SCHEMA topology;

COMMENT ON SCHEMA topology IS 'PostGIS Topology schema';

CREATE EXTENSION IF NOT EXISTS address_standardizer_data_us WITH SCHEMA public;

CREATE EXTENSION IF NOT EXISTS fuzzystrmatch WITH SCHEMA public;

CREATE EXTENSION IF NOT EXISTS pg_stat_statements WITH SCHEMA public;

CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public;

CREATE EXTENSION IF NOT EXISTS postgis_raster WITH SCHEMA public;

CREATE EXTENSION IF NOT EXISTS postgis_tiger_geocoder WITH SCHEMA tiger;

CREATE EXTENSION IF NOT EXISTS postgis_topology WITH SCHEMA topology;

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA crm;

CREATE TYPE billing.cc_type AS ENUM (
    'VISA',
    'MASTERCARD',
    'AMEX',
    'DISCOVER',
    'OTHER'
);

CREATE TYPE billing.invoice_reasons AS ENUM (
    'monthly_billing',
    'scheduled_service',
    'initial_service',
    'cancellation_idr',
    'add_on_service_initial'
);

CREATE TYPE crm.enum_etl_execution_step_entity AS ENUM (
    'appointments',
    'appointment_reminders',
    'chemical_uses',
    'contracts',
    'customers',
    'documents',
    'employees',
    'forms',
    'generic_flag_assignments',
    'generic_flags',
    'invoices',
    'notes',
    'payments',
    'payment_profiles',
    'routes',
    'service_types',
    'spots',
    'subscriptions'
);

CREATE TYPE customer.account_status AS ENUM (
    'active',
    'inactive',
    'frozen'
);

CREATE TYPE dbe_test.test_types AS ENUM (
    'payment_declined',
    'invoice_payment_declined',
    'appointment_no_show',
    'appointment_reminder',
    'appointment_completed',
    'appointment_rescheduled',
    'welcome_email',
    'appointment_cancelled',
    'subscription_change',
    'unpaid_invoice'
);

CREATE TYPE field_operations.notification_channels AS ENUM (
    'email',
    'sms'
);

CREATE TYPE notifications.batch_type AS ENUM (
    'appointment_reminder'
);

CREATE TYPE notifications.methods AS ENUM (
    'SMS',
    'Email'
);

CREATE TYPE notifications.notification_types AS ENUM (
    'payment_declined',
    'invoice_payment_declined',
    'appointment_no_show',
    'appointment_reminder',
    'appointment_completed',
    'appointment_rescheduled',
    'welcome_email',
    'appointment_cancelled',
    'subscription_change',
    'unpaid_invoice',
    'direct_welcome_email',
    'termite_email'
);

CREATE TYPE organization.permission_actions AS ENUM (
    'browse',
    'read',
    'edit',
    'add',
    'delete',
    'login',
    '*'
);

CREATE TYPE public.currency_code AS ENUM (
    'USD',
    'CAD'
);

CREATE TYPE public.namespace AS ENUM (
    'customer',
    'organization'
);

CREATE TYPE public.notification_statuses AS ENUM (
    'delayed',
    'sent',
    'skipped',
    'failed',
    'one_time_delayed'
);

CREATE FUNCTION public.validate_urn(input_string text) RETURNS text
    LANGUAGE plpgsql
    AS $$
DECLARE
    parts text[];
BEGIN
    IF input_string IS NULL THEN
        RETURN NULL;
    END IF;

    parts := string_to_array(input_string, ':');

    -- Check if the string has exactly four parts
    IF array_length(parts, 1) <> 5 THEN
        RAISE EXCEPTION 'Invalid number of parts in the urn';
    END IF;

    IF parts[1] != 'urn' THEN
        RAISE EXCEPTION 'Invalid urn';
    END IF;

    IF parts[2] != current_database() THEN
        RAISE EXCEPTION 'Invalid tenant in urn';
    END IF;

    IF parts[3]::urn_schema IS NULL THEN
        RAISE EXCEPTION 'Invalid schema in urn';
    END IF;

    IF parts[4]::urn_entity IS NULL THEN
        RAISE EXCEPTION 'Invalid entity/table in urn';
    END IF;

    -- Return the parsed parts as a record
    RETURN input_string;
END;
$$;

CREATE DOMAIN public.urn AS text
	CONSTRAINT urn_check CHECK ((VALUE ~ '^[^:]+:[^:]+:[^:]+:.+'::text))
	CONSTRAINT validate_urn CHECK ((VALUE = public.validate_urn(VALUE)));

CREATE TYPE public.urn_entity AS ENUM (
    'customers',
    'users',
    'api_accounts'
);

CREATE TYPE public.urn_schema AS ENUM (
    'customer',
    'organization'
);

CREATE TYPE public.user_types AS ENUM (
    'sales_rep',
    'office_manager'
);

CREATE FUNCTION billing.invoices_update_ledger() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin

    -- if hard-deleted and was previously part of the balance (is_active = true)
    if TG_OP = 'DELETE' and old.is_active = true then
        update billing.ledger
        set balance = balance - old.total -- removes invoice from balance
        where account_id = old.account_id;

        delete from billing.ledger_transactions
        where invoice_id = old.id;

    -- if soft-deleted and was previously part of the balance (is_active = true)
    elsif TG_OP = 'UPDATE' and new.deleted_at is not null and old.deleted_at is null and old.is_active = true then
        update billing.ledger
        set balance = balance - old.total -- removes invoice from balance and uses old.total in case that was changed with the soft delete
        where account_id = new.account_id;

        update billing.ledger_transactions
        set deleted_at = new.deleted_at, deleted_by = new.deleted_by
        where invoice_id = old.id;

    -- if soft-delete undone (RESTORE) and is_active = true
    elsif TG_OP = 'UPDATE' and new.deleted_at is null and old.deleted_at is not null and new.is_active = true then
        update billing.ledger
        set balance = balance + new.total
        where account_id = new.account_id;

        update billing.ledger_transactions
        set deleted_at = null, deleted_by = null
        where invoice_id = old.id;

    -- if inserted or updated and is active and total has changed
    elsif TG_OP = 'INSERT' OR TG_OP = 'UPDATE' and new.is_active = true and new.total is distinct from old.total then
        insert into billing.ledger
        (account_id, balance)
        values (new.account_id, new.total)
        on conflict (account_id) do update
        set balance = ledger.balance + (new.total - coalesce(old.total,0));

        insert into billing.ledger_transactions (account_id, invoice_id, amount)
        values (new.account_id, new.id, new.total)
        on conflict (invoice_id) do update
        set amount = new.total;

    -- if inserted and inactive
    elsif TG_OP = 'INSERT' and new.is_active = false then
        -- do nothing
        return new;

    -- if updated and deactivated
    elsif TG_OP = 'UPDATE' and new.is_active = false and old.is_active is true then
        update billing.ledger
        set balance = balance - old.total -- removes invoice from balance and uses old.total in case that value was changed too in this update
        where account_id = new.account_id;

        delete from billing.ledger_transactions
        where invoice_id = old.id;

    end if;

    return new;
end;
$$;

CREATE FUNCTION billing.payments_update_ledger() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin
        -- if hard-deleted and was a paid status
        if TG_OP = 'DELETE' and old.payment_status_id in (2,9) then
            update billing.ledger
            set balance = balance + old.amount -- adds the amount back to the balance
            where account_id = old.account_id;

            delete from billing.ledger_transactions
            where payment_id = old.id;

        -- if soft-deleted and was a paid status
        elsif TG_OP = 'UPDATE' and new.deleted_at is not null and old.deleted_at is null and old.payment_status_id in (2,9) then
            update billing.ledger
            set balance = balance + old.amount -- adds the amount back to the balance
            where account_id = old.account_id;

            update billing.ledger_transactions
            set deleted_at = new.deleted_at, deleted_by = new.deleted_by
            where payment_id = old.id;

        -- if soft-delete undone (RESTORE) and is valid status
        elsif TG_OP = 'UPDATE' and new.deleted_at is null and old.deleted_at is not null and new.payment_status_id in (2,9) then
            update billing.ledger
            set balance = balance + new.amount*-1
            where account_id = new.account_id;

            update billing.ledger_transactions
            set deleted_at = null, deleted_by = null
            where payment_id = new.id;

        -- if status changed to unpaid status
        elsif TG_OP = 'UPDATE' and new.payment_status_id not in (2,9) and old.payment_status_id in (2,9) then
            update billing.ledger
            set balance = balance + old.amount -- adds the amount back to the balance
            where account_id = new.account_id;

            delete from billing.ledger_transactions
            where payment_id = new.id;

        -- if inserted or updated and is a paid status
        elsif (TG_OP = 'INSERT' OR TG_OP = 'UPDATE') and new.payment_status_id in (2,9) then
            insert into billing.ledger
            (account_id, balance)
            values (new.account_id, new.amount*-1)
            on conflict (account_id) do update
            set balance = ledger.balance + ((new.amount*-1) - coalesce((old.amount*-1),0));

            insert into billing.ledger_transactions (account_id, payment_id, amount)
            values (new.account_id, new.id, new.amount*-1)
            on conflict (payment_id) do update
            set amount = new.amount*-1;

        end if;
    return new;
end;
$$;

CREATE FUNCTION datadog.explain_statement(l_query text, OUT explain json) RETURNS SETOF json
    LANGUAGE plpgsql STRICT SECURITY DEFINER
    AS $$
DECLARE
curs REFCURSOR;
plan JSON;

BEGIN
   OPEN curs FOR EXECUTE pg_catalog.concat('EXPLAIN (FORMAT JSON) ', l_query);
   FETCH curs INTO plan;
   CLOSE curs;
   RETURN QUERY SELECT plan;
END;
$$;

CREATE FUNCTION public._validate_json_schema_type(type text, data jsonb) RETURNS boolean
    LANGUAGE plpgsql IMMUTABLE
    AS $$
BEGIN
  IF type = 'integer' THEN
    IF jsonb_typeof(data) != 'number' THEN
      RETURN false;
    END IF;
    IF trunc(data::text::numeric) != data::text::numeric THEN
      RETURN false;
    END IF;
  ELSE
    IF type != jsonb_typeof(data) THEN
      RETURN false;
    END IF;
  END IF;
  RETURN true;
END;
$$;

CREATE FUNCTION public.awsdms_intercept_ddl() RETURNS event_trigger
    LANGUAGE plpgsql
    AS $$
  declare _qry text;
BEGIN
  if (tg_tag='CREATE TABLE' or tg_tag='ALTER TABLE' or tg_tag='DROP TABLE') then
	    SELECT current_query() into _qry;
	    insert into public.awsdms_ddl_audit
	    values
	    (
	    default,current_timestamp,current_user,cast(TXID_CURRENT()as varchar(16)),tg_tag,0,'',current_schema,_qry
	    );
	    delete from public.awsdms_ddl_audit;
 end if;
END;
$$;

CREATE FUNCTION public.calculate_polygon_stats(batch_size integer) RETURNS void
    LANGUAGE plpgsql
    AS $$
DECLARE
    max_polygon_id INTEGER;
    current_offset INTEGER := 0;
BEGIN
    -- Find the maximum polygon_id
    SELECT MAX(polygon_id) INTO max_polygon_id FROM spt.polygon;

    -- Drop the existing polygon_stats table if it exists
    DROP TABLE IF EXISTS polygon_stats;

    -- Create a temporary table to store the polygon statistics
    CREATE TEMP TABLE polygon_stats (
        polygon_id INTEGER PRIMARY KEY,
        qualified_addresses INTEGER
    );

    -- Loop over batches until all polygons are processed
    WHILE current_offset <= max_polygon_id LOOP
        -- Insert data into polygon_stats for the current batch
        INSERT INTO polygon_stats
        SELECT
            polygon_id,
            COUNT(CASE WHEN is_qualified = true THEN true END) AS qualified_addresses
        FROM
            (
                SELECT *
                FROM spt.polygon
                ORDER BY polygon_id
                LIMIT batch_size
                OFFSET current_offset
            ) AS polygon
        INNER JOIN
            street_smarts.pins
        ON
            ST_Intersects(pins.point, polygon.boundary) AND pins.deleted_at IS NULL
        GROUP BY
            polygon_id
        ON CONFLICT (polygon_id) DO UPDATE
        SET
            qualified_addresses = EXCLUDED.qualified_addresses;
        RAISE NOTICE 'Processed batch starting at offset %', current_offset;
        -- Move to the next batch
        current_offset := current_offset + batch_size;
    END LOOP;
END;
$$;

CREATE FUNCTION public.create_audit_record() RETURNS trigger
    LANGUAGE plpgsql
    AS $_$
DECLARE
    _operation         text;
    DECLARE _json_diff jsonb;
    DECLARE _pestroutes_sync_status text;
BEGIN

    IF OLD is distinct from NEW AND TG_OP != 'DELETE' THEN
        select jsonb_object_agg(_old.key, jsonb_build_object('old', _old.value, 'new', _new.value))
        from jsonb_each(to_jsonb(old)) as _old(key, value)
                 join jsonb_each(to_jsonb(new)) as _new(key, value)
                      on (_old.key = _new.key) and _old.key not like 'pestroutes_%'
        where _old.value is distinct from _new.value
        into _json_diff;

        IF OLD.deleted_at is null and NEW.deleted_at is not null THEN
            _operation := 'SOFT_DELETE';
        ELSIF OLD.deleted_at is not null and NEW.deleted_at is null THEN
            _operation := 'RESTORE';
        ELSE
            _operation := TG_OP;
        END IF;

        -- only create an audit record if there is a difference between the old and new rows after excluding pestroutes columns
        IF _json_diff is not null or TG_OP in ('INSERT','DELETE') THEN
            _pestroutes_sync_status := case when current_user = 'lambda_pestroutes_etl' then 'NOT_APPLICABLE' else 'PENDING' end;

            EXECUTE format('
                    INSERT INTO audit.%I__%I
                    (table_id, operation, row_old, row_new, row_diff, db_user_name, db_user_ip, pestroutes_sync_status)
                    VALUES ( $1, $2, row_to_json($3), row_to_json($4), $5, $6, $7, $8)
                ', TG_TABLE_SCHEMA,
                           TG_TABLE_NAME) USING NEW.id, _operation, OLD, NEW, _json_diff, current_user, (SELECT inet_client_addr()), _pestroutes_sync_status;
        END IF;
        RETURN NEW;

    ElSIF TG_OP = 'DELETE' THEN
        EXECUTE format('
                INSERT INTO audit.%I__%I
                (table_id, operation, row_old, db_user_name, db_user_ip, pestroutes_sync_status)
                VALUES ( $1, $2, row_to_json($3), $4, $5, $6)
            ', TG_TABLE_SCHEMA,
                       TG_TABLE_NAME) USING OLD.id, 'HARD_DELETE', OLD, current_user, (SELECT inet_client_addr()), _pestroutes_sync_status;
        RETURN OLD;

    ELSE
        RETURN NEW;
    END IF;

END;
$_$;

CREATE FUNCTION public.dbe_create_group_role(p_database_name character varying, p_schema_name character varying, p_permission_type character) RETURNS void
    LANGUAGE plpgsql
    AS $$

DECLARE 
	group_role VARCHAR(255);
	p_permission TEXT;
BEGIN
	IF NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = p_database_name) THEN
	-- if database is empty or can't find such database
        RAISE EXCEPTION 'no database found';
    END IF;

	IF NOT EXISTS(SELECT schema_name FROM information_schema.schemata WHERE schema_name = p_schema_name) THEN
	-- if schema name is empty or not found
        RAISE EXCEPTION 'no schema found';
    END IF; 
	
    -- Create write_only role and grant DML access
    IF p_permission_type = 'w' THEN
		group_role := 'write_only_' || p_schema_name;
		p_permission := 'SELECT, INSERT, UPDATE';	
	ELSIF p_permission_type = 'r' THEN
		group_role := 'read_only_' || p_schema_name;
		p_permission := 'SELECT';	
	ELSE 
		RAISE EXCEPTION 'Invalid permission type. Use ''w'' for write or ''r'' for read.';
    END IF;
	
	-- check to see if we have the role already+
	IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = group_role) THEN
		RAISE EXCEPTION 'group role exists';
	END IF;

	--Create the Role:
    EXECUTE 'CREATE ROLE ' || group_role;
	IF EXISTS (SELECT 1 FROM pg_database WHERE datname = p_database_name) THEN
        	RAISE Notice 'database name exists: %',  p_database_name;
	END IF;
	-- Grant CONNECT Permission:
	EXECUTE 'GRANT CONNECT ON DATABASE ' || p_database_name|| ' TO ' || group_role;
		
	-- Grant USAGE , DML permissions on Schema and Functions and Sequences:
	EXECUTE 'GRANT USAGE ON SCHEMA ' || p_schema_name ||' TO ' || group_role;
    EXECUTE 'GRANT ' || p_permission ||' ON ALL TABLES IN SCHEMA ' || p_schema_name || ' TO ' || group_role;
    -- EXECUTE 'GRANT USAGE, EXECUTE ON ALL FUNCTIONS IN SCHEMA ' || p_schema_name || ' TO ' || group_role;
	EXECUTE 'GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA ' || p_schema_name || ' TO ' || group_role;
		
	-- Alter Default Privileges:
	-- EXECUTE 'ALTER DEFAULT PRIVILEGES IN SCHEMA ' || p_schema_name;
	-- EXECUTE 'GRANT ' || p_permission || ' ON TABLES TO ' || group_role;
	EXECUTE 'ALTER DEFAULT PRIVILEGES IN SCHEMA ' || p_schema_name ||' GRANT ' || p_permission || ' ON TABLES TO ' || group_role;
END;
$$;

CREATE FUNCTION public.dbe_create_list_of_users(users_array text[]) RETURNS TABLE(user_account text, result_password text, role_assigned text)
    LANGUAGE plpgsql
    AS $$

DECLARE
		user_record RECORD;
		p_user_name varchar(255);
		p_schema_name varchar(255);
		p_permission_type VARCHAR(10);
        group_role VARCHAR(255);
		p_password VARCHAR(36);
BEGIN
	FOR user_record IN SELECT unnest(users_array) AS parsed_record
	LOOP
		SELECT split_part(user_record.parsed_record, '-', 1) INTO p_user_name;
        SELECT split_part(user_record.parsed_record, '-', 2) INTO p_schema_name;
		SELECT split_part(user_record.parsed_record, '-', 3) INTO p_permission_type;
		-- user name can not be null or empty
		IF p_user_name IS NULL OR p_user_name = '' THEN
        	RAISE EXCEPTION 'user name cannot be empty';
    	END IF; 
		-- schema name can not be null or empty
		IF p_schema_name IS NULL OR p_schema_name = '' THEN
			RAISE EXCEPTION 'schema name can not be null or empty';
		END IF;
		-- set the group role
		IF p_permission_type = 'w' THEN
			IF p_schema_name = 'all' THEN
				group_role := 'write_all_schemas';
			ELSE
				group_role := 'write_only_' || p_schema_name;
			END IF;
		ELSE -- everything else will be read only
			IF p_schema_name = 'all' THEN
				group_role := 'read_all_schemas';
			ELSE
				group_role := 'read_only_' || p_schema_name;
			END IF;
		END IF;
		IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = group_role) THEN
			 RAISE EXCEPTION 'group role not exists';
		END IF;
	
		-- Check if the user already exists
    	IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = p_user_name) THEN
        	RAISE Notice 'user name exists: %',  p_user_name;
			p_password := '';
		ELSE
			--random generate the password, set the length to 24?
			p_password := substring(replace(gen_random_uuid()::text, '-', ''), 1, 36);
			-- Create the role with the provided password
			EXECUTE 'CREATE ROLE ' || p_user_name || ' WITH
				LOGIN
				NOSUPERUSER
				INHERIT
				NOCREATEDB
				NOCREATEROLE
				NOREPLICATION
				PASSWORD ' || quote_literal(p_password);
    	END IF;
		-- assign the group role     
		-- EXECUTE 'GRANT ' || p_user_name || ' TO ' || group_role;
		EXECUTE 'GRANT ' || group_role || ' TO ' || p_user_name;
		user_account := p_user_name;
		result_password := p_password;
		role_assigned := group_role;
		RETURN NEXT;
    END LOOP;

END;

$$;

CREATE FUNCTION public.dbe_create_user(p_user_name character varying, p_schema_name character varying, p_permission_type character varying) RETURNS void
    LANGUAGE plpgsql
    AS $$

DECLARE
        group_role VARCHAR(255);
		p_password VARCHAR(24);
BEGIN
	-- user name can not be null or empty
	IF p_user_name IS NULL OR p_user_name = '' THEN
        RAISE EXCEPTION 'user name cannot be empty';
    END IF; 
	
	-- Check if the user already exists
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = p_user_name) THEN
        RAISE EXCEPTION 'user name already exists';
    END IF;
	
	-- schema name can not be null or empty
	IF p_schema_name IS NULL OR p_schema_name = '' THEN
		RAISE EXCEPTION 'schema name can not be null or empty';
	END IF;
	
	-- set the group role
	IF p_permission_type = 'w' THEN
		IF p_schema_name = 'all' THEN
			group_role := 'write_all_schemas';
		ELSE
			group_role := 'write_only_' || p_schema_name;
		END IF;
	ELSE -- everything else will be read only
		IF p_schema_name = 'all' THEN
			group_role := 'read_all_schemas';
		ELSE
			group_role := 'read_only_' || p_schema_name;
		END IF;
	END IF;
		
	IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = group_role) THEN
		RAISE EXCEPTION 'group role not exists';
	END IF;

	--random generate the password, set the length to 24?
	p_password := substring(replace(gen_random_uuid()::text, '-', ''), 1, 24);
    
    -- Create the role with the provided password
    EXECUTE 'CREATE ROLE ' || p_user_name || ' WITH
        LOGIN
        NOSUPERUSER
        INHERIT
        NOCREATEDB
        NOCREATEROLE
        NOREPLICATION
        PASSWORD ' || quote_literal(p_password);
		
	-- assign the group role     
	EXECUTE 'GRANT ' || p_user_name || ' TO ' || group_role;
		
END;
$$;

CREATE FUNCTION public.dbe_revoke_account_with_permission(p_account_name text, p_drop_account boolean DEFAULT false) RETURNS SETOF text
    LANGUAGE plpgsql
    AS $$
DECLARE 
    schema_name text;
BEGIN
    -- Loop through all schema names that associated with the account
    FOR schema_name IN (
        SELECT DISTINCT n.nspname
        FROM pg_catalog.pg_class c
        LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
        WHERE pg_catalog.array_to_string(c.relacl, E'\n') LIKE '%' || p_account_name || '%'
        ORDER BY n.nspname
    )
    LOOP
        -- Return the schema name
        -- RETURN NEXT schema_name;
		execute 'REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA ' || schema_name ||' FROM ' || p_account_name;
		execute 'REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA ' || schema_name ||' FROM ' || p_account_name;
		execute 'REVOKE ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA ' || schema_name ||' FROM ' || p_account_name;
		execute 'REVOKE ALL PRIVILEGES ON SCHEMA '|| schema_name ||' FROM ' || p_account_name;
		execute 'ALTER DEFAULT PRIVILEGES IN SCHEMA ' ||schema_name || ' REVOKE ALL ON TABLES FROM ' || p_account_name;
		execute 'REVOKE USAGE ON SCHEMA ' || schema_name || ' FROM ' || p_account_name;
		execute 'REVOKE CONNECT ON DATABASE aptive FROM ' || p_account_name;
    	RETURN NEXT schema_name;
	END LOOP;
	IF p_drop_account THEN
            EXECUTE 'DROP USER IF EXISTS ' || p_account_name;
        END IF;
    RETURN;
END
$$;

CREATE FUNCTION public.ddl_event_trigger() RETURNS event_trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    PERFORM enforce_table_requirements();
END;
$$;

CREATE FUNCTION public.enforce_table_requirements() RETURNS void
    LANGUAGE plpgsql
    AS $$
DECLARE
    _schema_name text;
    _table_name text;
    _schema_table text;
    _primary_key_column_name text;
    _table_id_data_type text;
BEGIN
    -- Get the name of the table from the event data
    SELECT object_identity, SPLIT_PART(object_identity,'.',1), SPLIT_PART(object_identity,'.',2) INTO _schema_table, _schema_name, _table_name
    FROM pg_event_trigger_ddl_commands()
    WHERE command_tag = 'CREATE TABLE';

    -- If the event is for a new or altered table and its not a temp table
    IF _table_name IS NOT NULL and _schema_name not in ('pg_temp', 'audit') THEN
        -- Ensure created_by, updated_by, deleted_by, created_at, updated_at, and deleted_at columns exist
        EXECUTE 'ALTER TABLE ' || _schema_table || ' DROP COLUMN IF EXISTS created_by';
        EXECUTE 'ALTER TABLE ' || _schema_table || ' DROP COLUMN IF EXISTS updated_by';
        EXECUTE 'ALTER TABLE ' || _schema_table || ' DROP COLUMN IF EXISTS deleted_by';
        EXECUTE 'ALTER TABLE ' || _schema_table || ' DROP COLUMN IF EXISTS created_at';
        EXECUTE 'ALTER TABLE ' || _schema_table || ' DROP COLUMN IF EXISTS updated_at';
        EXECUTE 'ALTER TABLE ' || _schema_table || ' DROP COLUMN IF EXISTS deleted_at';

        EXECUTE 'ALTER TABLE ' || _schema_table || ' ADD COLUMN created_by urn';

        EXECUTE 'ALTER TABLE ' || _schema_table || ' ADD COLUMN updated_by urn';

        EXECUTE 'ALTER TABLE ' || _schema_table || ' ADD COLUMN deleted_by urn';

        EXECUTE 'ALTER TABLE ' || _schema_table || ' ADD COLUMN created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL DEFAULT NOW()';

        EXECUTE 'ALTER TABLE ' || _schema_table || ' ADD COLUMN updated_at TIMESTAMP(6) WITH TIME ZONE NOT NULL DEFAULT NOW()';

        EXECUTE 'ALTER TABLE ' || _schema_table || ' ADD COLUMN deleted_at TIMESTAMP(6) WITH TIME ZONE';

        -- adds indexes to the table on created_at, updated_at, created_by, updated_by, deleted_by
        EXECUTE 'CREATE INDEX IF NOT EXISTS ' || _table_name || '_created_at_idx ON ' || _schema_table || ' (created_at)';
        EXECUTE 'CREATE INDEX IF NOT EXISTS ' || _table_name || '_updated_at_idx ON ' || _schema_table || ' (updated_at)';
        EXECUTE 'CREATE INDEX IF NOT EXISTS ' || _table_name || '_deleted_at_idx ON ' || _schema_table || ' (deleted_at)';
        EXECUTE 'CREATE INDEX IF NOT EXISTS ' || _table_name || '_created_by_idx ON ' || _schema_table || ' (created_by)';
        EXECUTE 'CREATE INDEX IF NOT EXISTS ' || _table_name || '_updated_by_idx ON ' || _schema_table || ' (updated_by)';
        EXECUTE 'CREATE INDEX IF NOT EXISTS ' || _table_name || '_deleted_by_idx ON ' || _schema_table || ' (deleted_by)';

        -- select the primary key column name into a variable
        SELECT column_name INTO _primary_key_column_name
        FROM information_schema.key_column_usage
        WHERE table_schema = _schema_name
          AND table_name = _table_name
          AND constraint_name IN (
            SELECT constraint_name
            FROM information_schema.table_constraints
            WHERE table_schema = _schema_name
              AND table_name = _table_name
              AND constraint_type = 'PRIMARY KEY'
            );

        SELECT data_type INTO _table_id_data_type
        FROM information_schema.columns
        WHERE table_schema = _schema_name
            AND table_name = _table_name
            AND column_name = _primary_key_column_name
        LIMIT 1;

                -- Create the audit.billing__invoices table if not exists
        EXECUTE 'CREATE TABLE IF NOT EXISTS audit.'||_schema_name||'__'||_table_name||' (
            id uuid DEFAULT gen_random_uuid() NOT NULL PRIMARY KEY,
            table_id ' || _table_id_data_type || ' NOT NULL,
            operation text,
            row_old jsonb,
            row_new jsonb,
            row_diff jsonb,
            db_user_name text NOT NULL,
            db_user_ip text,
            pestroutes_sync_status text,
            pestroutes_synced_at timestamp(6) WITH TIME ZONE,
            lambda_request_ids char(36)[],
            created_by urn,
            updated_by urn,
            deleted_by urn,
            created_at timestamp(6) WITH TIME ZONE DEFAULT NOW() NOT NULL,
            updated_at timestamp(6) WITH TIME ZONE DEFAULT NOW() NOT NULL,
            deleted_at timestamp(6) WITH TIME ZONE,
            pestroutes_sync_error text
        )';
        -- adds separate indexes to audit table on table_id, created_at, updated_at, created_by, updated_by, deleted_by
        EXECUTE 'CREATE INDEX IF NOT EXISTS idx_' || _schema_name || '__' || _table_name || '_table_id ON audit.' || _schema_name || '__' || _table_name || ' (table_id)';
        EXECUTE 'CREATE INDEX IF NOT EXISTS idx_' || _schema_name || '__' || _table_name || '_created_at ON audit.' || _schema_name || '__' || _table_name || ' (created_at)';
        EXECUTE 'CREATE INDEX IF NOT EXISTS idx_' || _schema_name || '__' || _table_name || '_updated_at ON audit.' || _schema_name || '__' || _table_name || ' (updated_at)';
        EXECUTE 'CREATE INDEX IF NOT EXISTS idx_' || _schema_name || '__' || _table_name || '_created_by ON audit.' || _schema_name || '__' || _table_name || ' (created_by)';
        EXECUTE 'CREATE INDEX IF NOT EXISTS idx_' || _schema_name || '__' || _table_name || '_updated_by ON audit.' || _schema_name || '__' || _table_name || ' (updated_by)';
        EXECUTE 'CREATE INDEX IF NOT EXISTS idx_' || _schema_name || '__' || _table_name || '_deleted_by ON audit.' || _schema_name || '__' || _table_name || ' (deleted_by)';
        EXECUTE 'CREATE INDEX IF NOT EXISTS idx_' || _schema_name || '__' || _table_name || '_pestroutes_sync_status_pending_only ON audit.' || _schema_name || '__' || _table_name || ' (pestroutes_sync_status) WHERE pestroutes_sync_status = ''PENDING''';

        -- Create a trigger that calls the create_audit_record function on INSERT, UPDATE, and DELETE
        EXECUTE 'CREATE TRIGGER '||_table_name||'_audit_record_trigger
                 AFTER INSERT OR UPDATE OR DELETE
                 ON ' || _schema_table || '
                 FOR EACH ROW
                 EXECUTE FUNCTION create_audit_record()';

        EXECUTE 'CREATE TRIGGER '||_table_name||'_update_updated_at
         BEFORE UPDATE
         ON  ' || _schema_table || '
         FOR EACH ROW
         EXECUTE FUNCTION update_updated_at()';
    END IF;
END;
$$;

CREATE FUNCTION public.exec(text) RETURNS text
    LANGUAGE plpgsql
    AS $_$
begin
    execute $1; return $1;
end;
$_$;

CREATE FUNCTION public.f_concat_ws(text, VARIADIC text[]) RETURNS text
    LANGUAGE sql IMMUTABLE PARALLEL SAFE
    AS $_$SELECT array_to_string($2, $1)$_$;

CREATE FUNCTION public.handle_timestamps_on_insert() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.created_at := now();
    NEW.updated_at := now();
    RETURN NEW;
END;
$$;

CREATE FUNCTION public.is_qualified_update_trigger_fnc() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF (TG_OP = 'INSERT') THEN
        INSERT INTO street_smarts.pin_history (pin_id, is_qualified, is_dropped)
        VALUES(NEW.id, NEW.is_qualified, NEW.is_dropped);
    END IF;

    IF (TG_OP = 'UPDATE') THEN
        IF (OLD.is_qualified <> NEW.is_qualified) or (OLD.is_dropped <> NEW.is_dropped) then
            INSERT INTO street_smarts.pin_history (pin_id, is_qualified, is_dropped)
            VALUES(NEW.id, NEW.is_qualified, NEW.is_dropped);
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

CREATE FUNCTION public.update_updated_at() RETURNS trigger
    LANGUAGE plpgsql
    AS $_$
DECLARE
    old_column_name TEXT;
    new_column_name TEXT;
    old_value TEXT;
    new_value TEXT;
BEGIN
    -- Compare each column individually, excluding the column named "pestroutes_json"
    FOR old_column_name IN SELECT column_name FROM information_schema.columns WHERE table_name = TG_TABLE_NAME AND table_schema = TG_TABLE_SCHEMA and column_name not like 'pestroutes_%'
    LOOP
        EXECUTE format('SELECT ($1).%I, ($2).%I', old_column_name, old_column_name)
            USING OLD, NEW
           INTO old_value, new_value;

        -- Compare values directly using IS DISTINCT FROM
        IF old_value IS DISTINCT FROM new_value THEN
            NEW.updated_at = NOW();
            EXIT; -- Exit loop if any difference is found
        END IF;
    END LOOP;

    RETURN NEW;
END;
$_$;

CREATE FUNCTION public.update_updated_at_v2() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- Check if the whole OLD and NEW records are different
    IF OLD IS DISTINCT FROM NEW THEN
        NEW.updated_at = NOW();
    END IF;

    RETURN NEW;
END;
$$;

CREATE FUNCTION public.validate_json_schema(schema jsonb, data jsonb, root_schema jsonb DEFAULT NULL::jsonb) RETURNS boolean
    LANGUAGE plpgsql IMMUTABLE
    AS $_$
DECLARE
  prop text;
  item jsonb;
  path text[];
  types text[];
  pattern text;
  props text[];
BEGIN
  IF root_schema IS NULL THEN
    root_schema = schema;
  END IF;

  IF schema ? 'type' THEN
    IF jsonb_typeof(schema->'type') = 'array' THEN
      types = ARRAY(SELECT jsonb_array_elements_text(schema->'type'));
    ELSE
      types = ARRAY[schema->>'type'];
    END IF;
    IF (SELECT NOT bool_or(public._validate_json_schema_type(type, data)) FROM unnest(types) type) THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'properties' THEN
    FOR prop IN SELECT jsonb_object_keys(schema->'properties') LOOP
      IF data ? prop AND NOT public.validate_json_schema(schema->'properties'->prop, data->prop, root_schema) THEN
        RETURN false;
      END IF;
    END LOOP;
  END IF;

  IF schema ? 'required' AND jsonb_typeof(data) = 'object' THEN
    IF NOT ARRAY(SELECT jsonb_object_keys(data)) @>
           ARRAY(SELECT jsonb_array_elements_text(schema->'required')) THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'items' AND jsonb_typeof(data) = 'array' THEN
    IF jsonb_typeof(schema->'items') = 'object' THEN
      FOR item IN SELECT jsonb_array_elements(data) LOOP
        IF NOT public.validate_json_schema(schema->'items', item, root_schema) THEN
          RETURN false;
        END IF;
      END LOOP;
    ELSE
      IF NOT (
        SELECT bool_and(i > jsonb_array_length(schema->'items') OR public.validate_json_schema(schema->'items'->(i::int - 1), elem, root_schema))
        FROM jsonb_array_elements(data) WITH ORDINALITY AS t(elem, i)
      ) THEN
        RETURN false;
      END IF;
    END IF;
  END IF;

  IF jsonb_typeof(schema->'additionalItems') = 'boolean' and NOT (schema->'additionalItems')::text::boolean AND jsonb_typeof(schema->'items') = 'array' THEN
    IF jsonb_array_length(data) > jsonb_array_length(schema->'items') THEN
      RETURN false;
    END IF;
  END IF;

  IF jsonb_typeof(schema->'additionalItems') = 'object' THEN
    IF NOT (
        SELECT bool_and(public.validate_json_schema(schema->'additionalItems', elem, root_schema))
        FROM jsonb_array_elements(data) WITH ORDINALITY AS t(elem, i)
        WHERE i > jsonb_array_length(schema->'items')
      ) THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'minimum' AND jsonb_typeof(data) = 'number' THEN
    IF data::text::numeric < (schema->>'minimum')::numeric THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'maximum' AND jsonb_typeof(data) = 'number' THEN
    IF data::text::numeric > (schema->>'maximum')::numeric THEN
      RETURN false;
    END IF;
  END IF;

  IF COALESCE((schema->'exclusiveMinimum')::text::bool, FALSE) THEN
    IF data::text::numeric = (schema->>'minimum')::numeric THEN
      RETURN false;
    END IF;
  END IF;

  IF COALESCE((schema->'exclusiveMaximum')::text::bool, FALSE) THEN
    IF data::text::numeric = (schema->>'maximum')::numeric THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'anyOf' THEN
    IF NOT (SELECT bool_or(public.validate_json_schema(sub_schema, data, root_schema)) FROM jsonb_array_elements(schema->'anyOf') sub_schema) THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'allOf' THEN
    IF NOT (SELECT bool_and(public.validate_json_schema(sub_schema, data, root_schema)) FROM jsonb_array_elements(schema->'allOf') sub_schema) THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'oneOf' THEN
    IF 1 != (SELECT COUNT(*) FROM jsonb_array_elements(schema->'oneOf') sub_schema WHERE public.validate_json_schema(sub_schema, data, root_schema)) THEN
      RETURN false;
    END IF;
  END IF;

  IF COALESCE((schema->'uniqueItems')::text::boolean, false) THEN
    IF (SELECT COUNT(*) FROM jsonb_array_elements(data)) != (SELECT count(DISTINCT val) FROM jsonb_array_elements(data) val) THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'additionalProperties' AND jsonb_typeof(data) = 'object' THEN
    props := ARRAY(
      SELECT key
      FROM jsonb_object_keys(data) key
      WHERE key NOT IN (SELECT jsonb_object_keys(schema->'properties'))
        AND NOT EXISTS (SELECT * FROM jsonb_object_keys(schema->'patternProperties') pat WHERE key ~ pat)
    );
    IF jsonb_typeof(schema->'additionalProperties') = 'boolean' THEN
      IF NOT (schema->'additionalProperties')::text::boolean AND jsonb_typeof(data) = 'object' AND NOT props <@ ARRAY(SELECT jsonb_object_keys(schema->'properties')) THEN
        RETURN false;
      END IF;
    ELSEIF NOT (
      SELECT bool_and(public.validate_json_schema(schema->'additionalProperties', data->key, root_schema))
      FROM unnest(props) key
    ) THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? '$ref' THEN
    path := ARRAY(
      SELECT regexp_replace(regexp_replace(path_part, '~1', '/'), '~0', '~')
      FROM UNNEST(regexp_split_to_array(schema->>'$ref', '/')) path_part
    );
    -- ASSERT path[1] = '#', 'only refs anchored at the root are supported';
    IF NOT public.validate_json_schema(root_schema #> path[2:array_length(path, 1)], data, root_schema) THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'enum' THEN
    IF NOT EXISTS (SELECT * FROM jsonb_array_elements(schema->'enum') val WHERE val = data) THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'minLength' AND jsonb_typeof(data) = 'string' THEN
    IF char_length(data #>> '{}') < (schema->>'minLength')::numeric THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'maxLength' AND jsonb_typeof(data) = 'string' THEN
    IF char_length(data #>> '{}') > (schema->>'maxLength')::numeric THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'not' THEN
    IF public.validate_json_schema(schema->'not', data, root_schema) THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'maxProperties' AND jsonb_typeof(data) = 'object' THEN
    IF (SELECT count(*) FROM jsonb_object_keys(data)) > (schema->>'maxProperties')::numeric THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'minProperties' AND jsonb_typeof(data) = 'object' THEN
    IF (SELECT count(*) FROM jsonb_object_keys(data)) < (schema->>'minProperties')::numeric THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'maxItems' AND jsonb_typeof(data) = 'array' THEN
    IF (SELECT count(*) FROM jsonb_array_elements(data)) > (schema->>'maxItems')::numeric THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'minItems' AND jsonb_typeof(data) = 'array' THEN
    IF (SELECT count(*) FROM jsonb_array_elements(data)) < (schema->>'minItems')::numeric THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'dependencies' THEN
    FOR prop IN SELECT jsonb_object_keys(schema->'dependencies') LOOP
      IF data ? prop THEN
        IF jsonb_typeof(schema->'dependencies'->prop) = 'array' THEN
          IF NOT (SELECT bool_and(data ? dep) FROM jsonb_array_elements_text(schema->'dependencies'->prop) dep) THEN
            RETURN false;
          END IF;
        ELSE
          IF NOT public.validate_json_schema(schema->'dependencies'->prop, data, root_schema) THEN
            RETURN false;
          END IF;
        END IF;
      END IF;
    END LOOP;
  END IF;

  IF schema ? 'pattern' AND jsonb_typeof(data) = 'string' THEN
    IF (data #>> '{}') !~ (schema->>'pattern') THEN
      RETURN false;
    END IF;
  END IF;

  IF schema ? 'patternProperties' AND jsonb_typeof(data) = 'object' THEN
    FOR prop IN SELECT jsonb_object_keys(data) LOOP
      FOR pattern IN SELECT jsonb_object_keys(schema->'patternProperties') LOOP
        RAISE NOTICE 'prop %s, pattern %, schema %', prop, pattern, schema->'patternProperties'->pattern;
        IF prop ~ pattern AND NOT public.validate_json_schema(schema->'patternProperties'->pattern, data->prop, root_schema) THEN
          RETURN false;
        END IF;
      END LOOP;
    END LOOP;
  END IF;

  IF schema ? 'multipleOf' AND jsonb_typeof(data) = 'number' THEN
    IF data::text::numeric % (schema->>'multipleOf')::numeric != 0 THEN
      RETURN false;
    END IF;
  END IF;

  RETURN true;
END;
$_$;

CREATE FUNCTION spt.update_team_extended() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin
    if new.boundary is not null then
        new.latitude = st_y(st_centroid(new.boundary));
        new.longitude = st_x(st_centroid(new.boundary));
    end if;
    new.updated_at = now();
    return new;
end;
$$;

CREATE FUNCTION street_smarts.exec(text) RETURNS text
    LANGUAGE plpgsql
    AS $_$ BEGIN EXECUTE $1; RETURN $1; END; $_$;

SET default_tablespace = '';

SET default_table_access_method = heap;

CREATE TABLE alterra_production.ecosure_contacts (
    id integer NOT NULL,
    user_id integer,
    lead_id integer,
    first_name character varying(64),
    last_name character varying(96),
    preferred_name character varying(255),
    mobile character varying(20),
    mobile2 character varying(20),
    emergency_contact_name character varying(100),
    emergency_phone_number character varying(20),
    alternate_email character varying(50),
    address1 character varying(255),
    address2 character varying(255),
    city character varying(64),
    state character(3),
    country integer,
    zip character varying(11),
    no_of_years integer,
    is_different_address character varying(5),
    permanent_address character varying(255),
    permanent_city character varying(64),
    permanent_state character(3),
    permanent_country integer,
    permanent_zip character varying(11),
    experience integer,
    experience_in_years integer,
    industry_coming_from character varying(255),
    number_of_accounts_previous_company integer,
    school_id integer,
    experience_in_industry integer,
    participated_in_a_company_meeting integer,
    made_travel_meeting integer,
    participated_in_company_activity integer,
    visited_company_building integer,
    attended_3_or_more_training_meetings integer,
    training_on_doors integer,
    met_with_owners integer,
    personality_test_score integer,
    personality_test_id character varying(255),
    start_date date,
    end_date date,
    is_subscribed character varying(200),
    up_front_pay integer,
    rent_situation character varying(255),
    created timestamp without time zone,
    modified timestamp without time zone,
    has_car character varying(10),
    has_segway character varying(10),
    expected_arrival_date date,
    polo_shirt_size character varying(256),
    hat_size character varying(256),
    jacket_size character varying(10),
    waist_size character varying(10),
    shoe_size character varying(255),
    marital_status character varying(10),
    spouse_name character varying(100),
    spouse_last_name character varying(50),
    dob character varying(20),
    age integer,
    place_of_birth character varying(100),
    birth_state character varying(10),
    other_birth_state character varying(50),
    ethnicity character varying(255),
    gender character varying(10),
    veteran character varying(5),
    height character varying(10),
    weight character varying(10),
    hair_color character varying(20),
    eye_color character varying(20),
    drivers_license_number character varying(255),
    drivers_license_expiration_date character varying(20),
    state_issued character varying(255),
    ss character varying(255),
    llc_name character varying(255),
    ein_number character varying(255),
    sales_license_number character varying(255),
    sales_state_issued character varying(255),
    expiration_date character varying(255),
    state_license_number character varying(255),
    state_license_expiration_date date,
    previous_sales_company character varying(255),
    number_of_accounts character varying(50),
    has_visible_markings character varying(5),
    visible_markings_reason character varying(255),
    is_us_citizen character varying(5),
    is_switchover integer,
    years_sold integer,
    created_at timestamp(6) without time zone,
    updated_at timestamp(6) without time zone
);

CREATE TABLE alterra_production.ecosure_customers (
    id bigint NOT NULL,
    company_id integer,
    salesrep_id bigint NOT NULL,
    office_id integer NOT NULL,
    season_id bigint NOT NULL,
    map_area_id bigint,
    first_name character varying(64),
    last_name character varying(96),
    phone character varying(20),
    alternate_phone character varying(20),
    email character varying(255),
    address_1 character varying(400),
    address_2 character varying(400),
    city character varying(64),
    state character varying(3),
    zip character varying(11),
    zip4 character varying(11),
    notes text,
    quarterly_price real,
    initial_price real,
    program_sales_value real,
    plan_id smallint,
    addons_initials numeric(10,2),
    billing_type character varying(9),
    annual_recurring_services smallint,
    latitude real,
    longitude real,
    created timestamp without time zone,
    modified timestamp without time zone,
    save_created timestamp without time zone NOT NULL,
    servsuit_branch_id integer NOT NULL,
    is_shown smallint,
    is_proof smallint NOT NULL,
    is_same_as_service_address smallint,
    account_id integer NOT NULL,
    crm_account_id character varying(50),
    signature_created timestamp without time zone NOT NULL,
    signature_modify timestamp without time zone NOT NULL,
    initial_created timestamp without time zone NOT NULL,
    initial_modify timestamp without time zone NOT NULL,
    version character varying(20) NOT NULL,
    is_dispatched smallint NOT NULL,
    is_disqualified smallint NOT NULL,
    comment text NOT NULL,
    agreement_length integer NOT NULL,
    easy_pay smallint NOT NULL,
    is_cc smallint NOT NULL,
    is_ach smallint NOT NULL,
    is_switchover smallint NOT NULL,
    nbm integer NOT NULL,
    has_switchover_proof smallint NOT NULL,
    is_phone_sale smallint NOT NULL,
    phone_sales_time_slot text,
    is_saved smallint NOT NULL,
    is_qualified smallint,
    is_deleted smallint NOT NULL,
    is_cleared integer NOT NULL,
    aws_filename_for_easypay character varying(255) NOT NULL,
    credit_card_token character varying(255) NOT NULL,
    donate numeric(6,2) NOT NULL,
    image_title character varying(255) NOT NULL,
    image_timestamp character varying(255) NOT NULL,
    booked timestamp without time zone,
    sales_goaptive integer,
    nbn_completed timestamp without time zone,
    nbn_status integer,
    nbn_id character varying(255),
    can_resignature integer,
    disqualified_in_tournament smallint NOT NULL,
    disqualified_in_masters smallint NOT NULL,
    welcome_letter_sent timestamp without time zone,
    welcome_letter_signed timestamp without time zone,
    branch_id integer,
    additional_pests_initial integer,
    additional_pests_quarterly integer,
    is_scheduled integer,
    non_serviceable integer,
    "messageId" character varying(255),
    is_sent integer NOT NULL,
    email_send_date timestamp without time zone NOT NULL,
    signature_request_id character varying(255) NOT NULL,
    envelope_id character varying(45),
    is_agreement_sign integer,
    is_signature_requested integer,
    signature_request_date timestamp without time zone NOT NULL,
    hellosign_file_url text NOT NULL,
    file_expair_on timestamp without time zone NOT NULL,
    custom_email_status integer NOT NULL,
    onlymosquito_treatment smallint NOT NULL,
    "messageDeduplicationId" character varying(99) NOT NULL,
    mosquito_per_treatment integer NOT NULL,
    base_branch_id integer NOT NULL,
    rep_license_no character varying(45) NOT NULL,
    target_date date NOT NULL,
    spot_description character varying(45) NOT NULL,
    pestroutes_appointment_id integer NOT NULL,
    pestroutes_employee_id integer,
    spot_id integer,
    spot_start_time character varying(8),
    spot_end_time character varying(8),
    spot_reservation_token character varying(255),
    pest_routes_subscription_id integer,
    pest_routes_payment_profile_id character varying(45),
    pin_id bigint,
    lead_id integer NOT NULL,
    billed_on integer,
    knock_id integer,
    appointment_status character varying(45),
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE alterra_production.ecosure_housing_tbl_team (
    id integer NOT NULL,
    team_id integer,
    team_name character varying(100),
    branch_id integer,
    primary_regional_id integer,
    primary_ram_id integer,
    reps_needed integer,
    sps_needed integer,
    team_start_date date,
    is_deleted integer,
    season_id integer,
    recruiting_season integer,
    created_at timestamp(6) without time zone,
    updated_at timestamp(6) without time zone
);

CREATE TABLE alterra_production.ecosure_housing_team_slots (
    id integer NOT NULL,
    reg_div_id integer,
    tbl_team_id integer,
    slot_position integer,
    is_filled character varying(24),
    rep_id integer,
    is_team_lead character varying(24),
    other_reg_user_id integer,
    "timestamp" timestamp without time zone,
    created_at timestamp(6) without time zone,
    updated_at timestamp(6) without time zone
);

CREATE TABLE alterra_production.ecosure_office_branches (
    id integer NOT NULL,
    name character varying(255),
    branch_manager_id integer,
    timezone character varying(45),
    ext integer,
    branch_phone character varying(255),
    address character varying(255),
    street_address_1 character varying(255),
    street_address_2 character varying(255),
    city character varying(255),
    state character varying(2),
    zip character varying(10),
    office_email character varying(255),
    lat double precision,
    long double precision,
    initial_service integer,
    quarterly_service integer,
    is_price integer,
    timezone_id integer,
    visible integer,
    vantiv_accountid character varying(255),
    vantive_accounttoken character varying(255),
    vantive_acceptorid character varying(255),
    pestroutes_alternative integer,
    housing_licensing_branch_id integer,
    approx_reps_needed integer,
    approx_reps_needed_pre integer,
    approx_reps_needed_post integer,
    is_saturday integer,
    is_sunday integer,
    holiday_list character varying(250),
    hellosignid character varying(99),
    docusign_id character varying(99),
    template_name character varying(99),
    template_year integer,
    license_number character varying(45),
    is_in_pestroutes integer,
    is_monthly_billing_option integer,
    require_tax_rate integer,
    require_tax_rate_state character varying(45),
    pestroutes_office_id smallint,
    spt_qualified_address_max_score integer,
    created_at timestamp(6) without time zone,
    updated_at timestamp(6) without time zone
);

CREATE TABLE alterra_production.ecosure_offices (
    id integer NOT NULL,
    name character varying(96),
    sales_goal integer,
    officeid character varying(96),
    manager_id integer,
    office_branch_id integer,
    avatar character varying(255),
    map_grid_id integer,
    use_office_map_area_restrictions integer,
    timezone_id integer,
    latitude double precision,
    longitude double precision,
    housing_need integer,
    is_pre_season integer,
    is_post_season integer,
    tl_id integer,
    rm_id integer,
    bm_id integer,
    no_of_wives integer,
    divisor smallint,
    created timestamp without time zone,
    modified timestamp without time zone,
    deleted integer,
    visible integer,
    ss_blue_pin_max_score smallint,
    ss_cells_max_average_score smallint,
    created_at timestamp(6) without time zone,
    updated_at timestamp(6) without time zone
);

CREATE TABLE alterra_production.ecosure_recruiting_seasons (
    id integer NOT NULL,
    name character varying(96),
    start_date date,
    end_date date,
    is_current integer,
    aptive_pay_year integer,
    is_sales integer,
    goal integer,
    created timestamp without time zone,
    modified timestamp without time zone,
    sales_season_id integer,
    graph_end_date date,
    created_at timestamp(6) without time zone,
    updated_at timestamp(6) without time zone
);

CREATE TABLE alterra_production.ecosure_recruits (
    id integer NOT NULL,
    company_id integer,
    user_id integer,
    lead_id integer,
    type character varying(55),
    status character varying(255),
    follow_up_date date,
    recruiting_season_id integer,
    is_approved integer,
    is_signed integer,
    converted_from_lead integer,
    date_signed timestamp without time zone,
    signed_by_id_back integer,
    signed_by_id integer,
    last_season_status character varying(255),
    school_id integer,
    other_school_name character varying(50),
    start_date date,
    end_date date,
    created timestamp without time zone,
    modified timestamp without time zone,
    deleted integer,
    last_contract_added_at timestamp without time zone,
    is_sent integer,
    note text,
    is_complete integer,
    is_manually_signed integer,
    sales_season_id integer,
    experience integer,
    is_manually_unsigned integer,
    face_to_face integer,
    temp_team_id integer,
    team_slot_id integer,
    trainer integer,
    primary_regional_id integer,
    assigned_reps_timestamp timestamp without time zone,
    preseason_temp_team_id integer,
    preseason_team_slot_id integer,
    preseason_trainer integer,
    postseason_temp_team_id integer,
    postseason_team_slot_id integer,
    postseason_trainer integer,
    kickstart_gear integer,
    label_id integer,
    is_updated_knockingdates integer,
    hide_contract integer,
    ssn character varying(10),
    created_at timestamp(6) without time zone,
    updated_at timestamp(6) without time zone
);

CREATE TABLE alterra_production.ecosure_scouts (
    id integer NOT NULL,
    company_id integer,
    scout_group_id integer,
    user_id integer,
    parent_scout_id integer,
    recruiting_season_id integer,
    can_sign_leads_without_approval integer,
    goal integer,
    region_goal integer,
    tasks text,
    created timestamp without time zone,
    modified timestamp without time zone,
    is_deleted integer,
    hierarchy_order integer,
    area_management integer,
    created_at timestamp(6) without time zone,
    updated_at timestamp(6) without time zone
);

CREATE TABLE alterra_production.ecosure_seasons (
    id bigint NOT NULL,
    company_id integer,
    name character varying(96) NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    summer_start_date date NOT NULL,
    summer_end_date date NOT NULL,
    is_current smallint NOT NULL,
    restrict_viewing smallint,
    sales_goal integer NOT NULL,
    created timestamp without time zone NOT NULL,
    modified timestamp without time zone NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE alterra_production.ecosure_users (
    id integer NOT NULL,
    email character varying(255),
    password character varying(50),
    active integer,
    tournament_active integer,
    knock_active integer,
    offseason_access character varying(255),
    old_offseason_access integer,
    recruiting_access integer,
    group_id integer,
    office_id integer,
    recruiting_office_id integer,
    temp_office_id integer,
    primary_regional_id integer,
    team_slot_id integer,
    housing_divisional_id integer,
    assigned_reps_timestamp timestamp without time zone,
    preseason_temp_office_id integer,
    preseason_team_slot_id integer,
    postseason_temp_office_id integer,
    postseason_team_slot_id integer,
    regional_manager_id_old integer,
    regional_manager_id integer,
    repid character varying(96),
    pestroutesid integer,
    pay_scale_id integer,
    bi_monthly_pay_overide integer,
    override_rent integer,
    account_percentage_override numeric,
    avatar character varying(255),
    last_login_date timestamp without time zone,
    can_view_office integer,
    sales_goal integer,
    region_sales_goal integer,
    created timestamp without time zone,
    modified timestamp without time zone,
    date_active date,
    deleted integer,
    master_tournament_active integer,
    per_rep_average integer,
    subscription_sent integer,
    unsubscribe integer,
    payroll_type integer,
    rent_type integer,
    fb_username character varying(200),
    regional_scout_id integer,
    servsuit_branch_id integer,
    payrollfilenumber character varying(30),
    allowednoproofpercentage numeric(10,2),
    parent_id integer,
    daysoff integer,
    tardies integer,
    hierarchy_order integer,
    license_number character varying(99),
    discount_code character varying(30),
    do_not_hire integer,
    do_not_hire_reason text,
    branch_access text,
    passport_img text,
    dl_img text,
    ssn_img text,
    is_profile_img_lock integer,
    is_test_user integer,
    workday_id character varying(100),
    is_workday_id_edited character varying(10),
    sign_img text,
    super_admin integer,
    password_hash_sha character varying(255),
    user_salt character varying(45),
    password_cost integer,
    company_id integer,
    app_access integer,
    is_unlocak_email_send integer,
    can_edit_branch_price integer,
    kickoff_sms_sent integer,
    kickoff_email_sent integer,
    created_at timestamp(6) without time zone,
    updated_at timestamp(6) without time zone,
    training_complete integer,
    hr_docs_complete integer,
    wotc_survey_completed bit(1),
    dealer_id integer
)
WITH (autovacuum_enabled='true', autovacuum_analyze_scale_factor='0.05', autovacuum_analyze_threshold='50', autovacuum_vacuum_threshold='10', autovacuum_vacuum_scale_factor='0.01');

CREATE TABLE audit.billing__decline_reasons (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__default_autopay_payment_methods (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__distinct_payment_methods (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__failed_jobs (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id bigint NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__failed_refund_payments (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__invoices (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__ledger (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    pestroutes_sync_error text,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE audit.billing__new_payments_with_last_four (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__payment_methods (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__payment_update_last4_log (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id bigint NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__payment_update_pestroutes_created_by_crm_log (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id bigint NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__payments (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

ALTER TABLE ONLY audit.billing__payments REPLICA IDENTITY FULL;

CREATE TABLE audit.billing__scheduled_payment_statuses (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__scheduled_payment_triggers (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__scheduled_payments (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__subscription_autopay_payment_methods (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__suspend_reasons (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.billing__test_payments (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.customer__accounts (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.customer__addresses (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.customer__contacts (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.customer__contracts (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.customer__documents (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.customer__forms (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.customer__notes (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.customer__subscriptions (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.dbe_test__test_tbl (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__appointments (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__aro_users (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__customer_property_details (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__monthly_financial_reports (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__notification_recipient_type (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__notification_recipients (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__notification_types (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__route_details (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__route_geometries (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__route_templates (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__routes (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__scheduled_route_details (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__scheduling_states (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__service_types (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__serviced_route_details (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.field_operations__treatment_states (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.notifications__cache (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.notifications__headshot_paths (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.notifications__incoming_mms_messages (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id bigint NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.notifications__logs (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id bigint NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.notifications__logs_email (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id bigint NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.notifications__notifications_sent_batches (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id bigint NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.notifications__sms_messages (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.notifications__sms_messages_media (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id integer NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.pestroutes__etl_writer_queue (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id bigint NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.pestroutes__etl_writer_queue_log (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id bigint NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.pestroutes__queue (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id bigint NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.pestroutes__queue_log (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id bigint NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE audit.spt__polygon_stats (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    table_id uuid NOT NULL,
    operation text,
    row_old jsonb,
    row_new jsonb,
    row_diff jsonb,
    db_user_name text NOT NULL,
    db_user_ip text,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    lambda_request_ids character(36)[],
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_sync_error text
);

CREATE TABLE auth.actions (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE auth.actions ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME auth.actions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE auth.api_account_roles (
    id integer NOT NULL,
    api_account_id uuid NOT NULL,
    role_id integer NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE auth.api_account_roles ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME auth.api_account_roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE auth.fields (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE auth.fields ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME auth.fields_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE auth.idp_roles (
    id integer NOT NULL,
    role_id integer NOT NULL,
    idp_group character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE auth.idp_roles ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME auth.idp_roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE auth.permissions (
    id integer NOT NULL,
    service_id integer NOT NULL,
    resource_id integer NOT NULL,
    field_id integer NOT NULL,
    action_id integer NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE auth.permissions ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME auth.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE auth.resources (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE auth.resources ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME auth.resources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE auth.role_permissions (
    id integer NOT NULL,
    role_id integer NOT NULL,
    permission_id integer NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE auth.role_permissions ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME auth.role_permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE auth.roles (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE auth.roles ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME auth.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE auth.services (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE auth.services ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME auth.services_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE auth.user_roles (
    id integer NOT NULL,
    user_id uuid NOT NULL,
    role_id integer NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE auth.user_roles ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME auth.user_roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE billing.account_updater_attempts (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    requested_by public.urn,
    requested_at timestamp(6) with time zone NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE billing.account_updater_attempts_methods (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    payment_method_id uuid NOT NULL,
    attempt_id uuid NOT NULL,
    sequence_number integer NOT NULL,
    original_token text NOT NULL,
    original_expiration_month integer NOT NULL,
    original_expiration_year integer NOT NULL,
    updated_token text,
    updated_expiration_month integer,
    updated_expiration_year integer,
    status text,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

COMMENT ON COLUMN billing.account_updater_attempts_methods.status IS 'TokenEx updating result status. Reference: https://docs.tokenex.com/docs/au-response-messages';

CREATE TABLE billing.decline_reasons (
    id integer NOT NULL,
    name character varying(32) NOT NULL,
    description character varying(128) NOT NULL,
    is_reprocessable boolean DEFAULT false NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE billing.decline_reasons ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME billing.decline_reasons_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE billing.default_autopay_payment_methods (
    id integer NOT NULL,
    account_id uuid NOT NULL,
    payment_method_id uuid NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE billing.default_autopay_payment_methods ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME billing.default_autopay_payment_methods_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE billing.failed_jobs (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE billing.failed_jobs ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME billing.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE billing.failed_refund_payments (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    original_payment_id uuid NOT NULL,
    refund_payment_id uuid NOT NULL,
    account_id uuid NOT NULL,
    amount integer NOT NULL,
    failed_at timestamp(6) with time zone NOT NULL,
    failure_reason character varying(128) NOT NULL,
    report_sent_at timestamp(6) with time zone DEFAULT NULL::timestamp with time zone,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

COMMENT ON COLUMN billing.failed_refund_payments.original_payment_id IS 'The identifier of the original payment that failed to be refunded';

COMMENT ON COLUMN billing.failed_refund_payments.refund_payment_id IS 'The identifier of the refund payment record that failed';

COMMENT ON COLUMN billing.failed_refund_payments.amount IS 'The requested amount to be refunded';

COMMENT ON COLUMN billing.failed_refund_payments.failed_at IS 'The date and time when the refund failed';

COMMENT ON COLUMN billing.failed_refund_payments.failure_reason IS 'The reason why the refund failed (from Gateway)';

COMMENT ON COLUMN billing.failed_refund_payments.report_sent_at IS 'If it was included in a report, the date and time when it was sent';

CREATE TABLE billing.invoice_items (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    external_ref_id integer,
    invoice_id uuid NOT NULL,
    quantity integer NOT NULL,
    amount integer NOT NULL,
    description character varying(255) NOT NULL,
    is_taxable boolean NOT NULL,
    pestroutes_invoice_id integer,
    pestroutes_product_id integer,
    pestroutes_service_type_id integer,
    pestroutes_created_at timestamp(0) with time zone,
    pestroutes_updated_at timestamp(0) with time zone,
    pestroutes_json jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    service_type_id integer
);

CREATE TABLE billing.invoices (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    external_ref_id integer,
    account_id uuid NOT NULL,
    subscription_id uuid,
    service_type_id integer,
    is_active boolean,
    subtotal bigint NOT NULL,
    tax_rate double precision NOT NULL,
    total bigint NOT NULL,
    balance integer NOT NULL,
    currency_code public.currency_code DEFAULT 'USD'::public.currency_code NOT NULL,
    service_charge integer NOT NULL,
    invoiced_at timestamp(6) with time zone,
    pestroutes_customer_id integer,
    pestroutes_subscription_id integer,
    pestroutes_service_type_id integer,
    pestroutes_created_by integer,
    pestroutes_created_at timestamp(0) with time zone,
    pestroutes_invoiced_at timestamp(0) with time zone,
    pestroutes_updated_at timestamp(0) with time zone,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_json jsonb,
    in_collections boolean DEFAULT false NOT NULL,
    is_written_off boolean DEFAULT false NOT NULL,
    reason billing.invoice_reasons
);

COMMENT ON COLUMN billing.invoices.external_ref_id IS '{"pestroutes_column_name": "ticketID"}';

COMMENT ON COLUMN billing.invoices.is_active IS '{"pestroutes_column_name": "active"}';

COMMENT ON COLUMN billing.invoices.subtotal IS '{"pestroutes_column_name": "subTotal"}';

COMMENT ON COLUMN billing.invoices.tax_rate IS '{"pestroutes_column_name": "taxRate"}';

COMMENT ON COLUMN billing.invoices.total IS '{"pestroutes_column_name": "total"}';

COMMENT ON COLUMN billing.invoices.balance IS '{"pestroutes_column_name": "balance"}';

COMMENT ON COLUMN billing.invoices.service_charge IS '{"pestroutes_column_name": "serviceCharge"}';

COMMENT ON COLUMN billing.invoices.pestroutes_customer_id IS '{"pestroutes_column_name": "customerID"}';

COMMENT ON COLUMN billing.invoices.pestroutes_subscription_id IS '{"pestroutes_column_name": "subscriptionID"}';

COMMENT ON COLUMN billing.invoices.pestroutes_service_type_id IS '{"pestroutes_column_name": "serviceID"}';

COMMENT ON COLUMN billing.invoices.pestroutes_created_by IS '{"pestroutes_column_name": "createdBy"}';

COMMENT ON COLUMN billing.invoices.pestroutes_created_at IS '{"pestroutes_column_name": "dateCreated"}';

COMMENT ON COLUMN billing.invoices.pestroutes_invoiced_at IS '{"pestroutes_column_name": "invoiceDate"}';

COMMENT ON COLUMN billing.invoices.pestroutes_updated_at IS '{"pestroutes_column_name": "dateUpdated"}';

COMMENT ON COLUMN billing.invoices.is_written_off IS 'To flag an invoice as bad debt expense (write them off)';

CREATE TABLE billing.ledger (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    account_id uuid NOT NULL,
    balance integer DEFAULT 0,
    balance_age_in_days integer,
    autopay_payment_method_id uuid,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE billing.ledger_transactions (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    account_id uuid NOT NULL,
    payment_id uuid,
    invoice_id uuid,
    amount integer NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE billing.payment_gateways (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    description character varying(255) NOT NULL,
    is_hidden boolean DEFAULT false NOT NULL,
    is_enabled boolean DEFAULT true NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE billing.payment_gateways ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME billing.payment_gateways_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE billing.payment_invoice_allocations (
    payment_id uuid NOT NULL,
    invoice_id uuid NOT NULL,
    amount bigint NOT NULL,
    pestroutes_payment_id integer,
    pestroutes_invoice_id integer,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    tax_amount integer DEFAULT 0 NOT NULL
);

CREATE TABLE billing.payment_methods (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    external_ref_id integer,
    account_id uuid NOT NULL,
    payment_gateway_id integer NOT NULL,
    payment_type_id integer NOT NULL,
    ach_account_number_encrypted character varying(255),
    ach_routing_number character(9),
    ach_account_type character varying(255),
    ach_token character varying(255),
    cc_token character varying(255),
    cc_expiration_month integer,
    cc_expiration_year integer,
    name_on_account character varying(255),
    address_line1 character varying(255),
    address_line2 character varying(255),
    email character varying(255),
    city character varying(255),
    province character(2),
    postal_code character varying(24),
    country_code character(2),
    is_primary boolean DEFAULT false NOT NULL,
    last_four character(4),
    pestroutes_customer_id integer,
    pestroutes_created_by integer,
    pestroutes_payment_method_id integer,
    pestroutes_status_id integer,
    pestroutes_ach_account_type_id integer,
    pestroutes_ach_check_type_id integer,
    pestroutes_payment_hold_date timestamp(0) with time zone,
    pestroutes_created_at timestamp(0) with time zone,
    pestroutes_updated_at timestamp(0) with time zone,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    ach_bank_name character varying(255),
    cc_type billing.cc_type,
    pestroutes_json jsonb,
    payment_hold_date timestamp(0) with time zone,
    pestroutes_metadata jsonb,
    pestroutes_data_link_alias character varying(50) DEFAULT NULL::character varying
);

CREATE TABLE billing.payment_statuses (
    id integer NOT NULL,
    external_ref_id integer,
    name character varying(255) NOT NULL,
    description character varying(255),
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE billing.payment_statuses ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME billing.payment_statuses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE billing.payment_types (
    id integer NOT NULL,
    external_ref_id integer,
    name character varying(255) NOT NULL,
    description character varying(255),
    is_hidden boolean DEFAULT false NOT NULL,
    is_enabled boolean DEFAULT true NOT NULL,
    sort_order integer NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE billing.payment_types ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME billing.payment_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE billing.payment_update_pestroutes_created_by_crm_log (
    id bigint NOT NULL,
    batch_start_id integer NOT NULL,
    error_message text NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE billing.payment_update_pestroutes_created_by_crm_log ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME billing.payment_update_pestroutes_created_by_crm_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE billing.payments (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    external_ref_id integer,
    account_id uuid NOT NULL,
    payment_type_id integer NOT NULL,
    payment_status_id integer NOT NULL,
    payment_method_id uuid,
    payment_gateway_id integer NOT NULL,
    currency_code public.currency_code DEFAULT 'USD'::public.currency_code NOT NULL,
    amount bigint NOT NULL,
    applied_amount bigint,
    notes text,
    processed_at timestamp(6) with time zone,
    notification_id integer,
    notification_sent_at timestamp(6) with time zone,
    is_office_payment boolean,
    is_collection_payment boolean,
    is_write_off boolean,
    pestroutes_customer_id integer,
    pestroutes_created_by integer,
    pestroutes_created_at timestamp(0) with time zone,
    pestroutes_updated_at timestamp(0) with time zone,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_json jsonb,
    original_payment_id uuid,
    pestroutes_original_payment_id integer,
    pestroutes_created_by_crm boolean DEFAULT true NOT NULL,
    is_batch_payment boolean DEFAULT false NOT NULL,
    suspended_at timestamp with time zone,
    suspend_reason_id integer,
    is_scheduled_payment boolean DEFAULT false NOT NULL,
    pestroutes_refund_processed_at timestamp(6) with time zone DEFAULT NULL::timestamp with time zone,
    pestroutes_metadata jsonb,
    terminated_by public.urn,
    terminated_at timestamp(6) with time zone,
    pestroutes_data_link_alias character varying(50) DEFAULT NULL::character varying
);

COMMENT ON COLUMN billing.payments.external_ref_id IS '{"pestroutes_column_name": "paymentID"}';

COMMENT ON COLUMN billing.payments.amount IS '{"pestroutes_column_name": "amount"}';

COMMENT ON COLUMN billing.payments.applied_amount IS '{"pestroutes_column_name": "appliedAmount"}';

COMMENT ON COLUMN billing.payments.notes IS '{"pestroutes_column_name": "notes"}';

COMMENT ON COLUMN billing.payments.is_office_payment IS '{"pestroutes_column_name": "officePayment"}';

COMMENT ON COLUMN billing.payments.is_collection_payment IS '{"pestroutes_column_name": "collectionPayment"}';

COMMENT ON COLUMN billing.payments.is_write_off IS '{"pestroutes_column_name": "writeOff"}';

COMMENT ON COLUMN billing.payments.pestroutes_customer_id IS '{"pestroutes_column_name": "customerID"}';

COMMENT ON COLUMN billing.payments.pestroutes_created_by IS '{"pestroutes_column_name": "employeeID"}';

COMMENT ON COLUMN billing.payments.pestroutes_created_at IS '{"pestroutes_column_name": "date"}';

COMMENT ON COLUMN billing.payments.pestroutes_updated_at IS '{"pestroutes_column_name": "dateUpdated"}';

COMMENT ON COLUMN billing.payments.is_batch_payment IS 'To flag a payment was processed via batch or not';

COMMENT ON COLUMN billing.payments.is_scheduled_payment IS 'Flag to determine if the payment was a scheduled payment';

COMMENT ON COLUMN billing.payments.pestroutes_refund_processed_at IS 'Timestamp when the eligible refund that was created in PestRoutes was processed by Payment Service';

COMMENT ON COLUMN billing.payments.terminated_by IS 'Stores the user who terminated the payment';

COMMENT ON COLUMN billing.payments.terminated_at IS 'Stores the time when the payment was terminated';

CREATE TABLE billing.scheduled_payment_statuses (
    id integer NOT NULL,
    name character varying(32) NOT NULL,
    description character varying(128),
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE billing.scheduled_payment_triggers (
    id integer NOT NULL,
    name character varying(32) NOT NULL,
    description character varying(128),
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE billing.scheduled_payments (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    account_id uuid NOT NULL,
    payment_method_id uuid NOT NULL,
    trigger_id integer NOT NULL,
    status_id integer NOT NULL,
    metadata jsonb NOT NULL,
    amount integer NOT NULL,
    payment_id uuid,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

COMMENT ON COLUMN billing.scheduled_payments.metadata IS 'Metadata for processing the scheduled payment by the trigger (e.g. subscription_id, appointment_id, etc)';

CREATE TABLE billing.subscription_autopay_payment_methods (
    id integer NOT NULL,
    subscription_id uuid NOT NULL,
    payment_method_id uuid NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE billing.subscription_autopay_payment_methods ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME billing.subscription_autopay_payment_methods_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE billing.suspend_reasons (
    id integer NOT NULL,
    name character varying(32) NOT NULL,
    description character varying(128) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE billing.suspend_reasons ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME billing.suspend_reasons_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE billing.transaction_types (
    id integer NOT NULL,
    name character varying(64) NOT NULL,
    description character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE billing.transaction_types ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME billing.transaction_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE billing.transactions (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    payment_id uuid NOT NULL,
    transaction_type_id integer NOT NULL,
    raw_request_log text,
    raw_response_log text,
    gateway_transaction_id text NOT NULL,
    gateway_response_code character varying(32) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    decline_reason_id integer
);

CREATE TABLE crm.generic_flags (
    id integer NOT NULL,
    area_id integer,
    external_ref_id integer,
    code text,
    description text,
    is_active boolean,
    type text,
    pestroutes_created_at timestamp(0) with time zone,
    pestroutes_updated_at timestamp(0) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6),
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6),
    pestroutes_json jsonb,
    pestroutes_metadata jsonb,
    pestroutes_data_link_alias character varying(50) DEFAULT NULL::character varying
);

COMMENT ON COLUMN crm.generic_flags.external_ref_id IS '{"pestroutes_column_name": "genericFlagID"}';

COMMENT ON COLUMN crm.generic_flags.code IS '{"pestroutes_column_name": "code"}';

COMMENT ON COLUMN crm.generic_flags.description IS '{"pestroutes_column_name": "description"}';

COMMENT ON COLUMN crm.generic_flags.is_active IS '{"pestroutes_column_name": "status"}';

COMMENT ON COLUMN crm.generic_flags.type IS '{"pestroutes_column_name": "type"}';

COMMENT ON COLUMN crm.generic_flags.pestroutes_created_at IS '{"pestroutes_column_name": "dateCreated", "timezone_type": "server"}';

COMMENT ON COLUMN crm.generic_flags.pestroutes_updated_at IS '{"pestroutes_column_name": "dateUpdated", "timezone_type": "server"}';

ALTER TABLE crm.generic_flags ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME crm.generic_flags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE customer.accounts (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    external_ref_id integer,
    area_id integer,
    dealer_id integer DEFAULT 1 NOT NULL,
    contact_id uuid NOT NULL,
    billing_contact_id uuid NOT NULL,
    service_address_id uuid NOT NULL,
    billing_address_id uuid NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    source character varying(255),
    autopay_type character varying(3),
    paid_in_full boolean,
    balance integer,
    balance_age integer,
    responsible_balance integer,
    responsible_balance_age integer,
    preferred_billing_day_of_month integer,
    payment_hold_date timestamp(6) with time zone,
    most_recent_credit_card_last_four character(4),
    most_recent_credit_card_exp_date character(5),
    sms_reminders boolean DEFAULT false,
    phone_reminders boolean DEFAULT false,
    email_reminders boolean DEFAULT false,
    tax_rate double precision,
    pestroutes_created_by integer,
    pestroutes_source_id integer,
    pestroutes_master_account integer,
    pestroutes_preferred_tech_id integer,
    pestroutes_customer_link character varying(255),
    pestroutes_created_at timestamp(0) with time zone,
    pestroutes_cancelled_at timestamp(0) with time zone,
    pestroutes_updated_at timestamp(0) with time zone,
    pestroutes_json jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    status customer.account_status DEFAULT 'active'::customer.account_status,
    pestroutes_metadata jsonb,
    pestroutes_data_link_alias character varying(50) DEFAULT NULL::character varying,
    receive_invoices_by_mail boolean DEFAULT false
);

COMMENT ON COLUMN customer.accounts.tax_rate IS '{"pestroutes_column_name": "taxRate"}';

CREATE TABLE customer.addresses (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    address character varying(255),
    city character varying(255),
    state character(2),
    postal_code character varying(24),
    country character varying(255),
    latitude double precision,
    longitude double precision,
    pestroutes_customer_id integer,
    pestroutes_address_type character varying(24),
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

COMMENT ON COLUMN customer.addresses.address IS '{"pestroutes_column_name": "address"}';

COMMENT ON COLUMN customer.addresses.city IS '{"pestroutes_column_name": "city"}';

COMMENT ON COLUMN customer.addresses.state IS '{"pestroutes_column_name": "state"}';

COMMENT ON COLUMN customer.addresses.pestroutes_customer_id IS '{"pestroutes_column_name": "customerID"}';

CREATE TABLE customer.cancellation_reasons (
    id integer NOT NULL,
    external_ref_id integer,
    name character varying(255) NOT NULL,
    is_active boolean NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE customer.cancellation_reasons ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME customer.cancellation_reasons_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE customer.contacts (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    company_name character varying(255),
    first_name character varying(255),
    last_name character varying(255),
    email character varying(255),
    phone1 character varying(24),
    phone2 character varying(24),
    pestroutes_customer_id integer,
    pestroutes_contact_type character varying(24),
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE customer.contracts (
    id integer NOT NULL,
    document_path character varying(255) NOT NULL,
    description character varying(255),
    state character varying(32) NOT NULL,
    pestroutes_customer_id integer,
    pestroutes_subscription_id integer,
    pestroutes_date_signed timestamp with time zone,
    pestroutes_date_added timestamp with time zone,
    pestroutes_date_updated timestamp with time zone,
    pestroutes_json jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE customer.documents (
    id integer NOT NULL,
    area_id integer,
    document_path text,
    description text,
    visible_to_customer boolean,
    visible_to_tech boolean,
    pestroutes_customer_id integer,
    pestroutes_appointment_id integer,
    pestroutes_added_by integer,
    pestroutes_prefix character varying(100),
    pestroutes_date_added timestamp with time zone,
    pestroutes_date_updated timestamp with time zone,
    pestroutes_json jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    account_id uuid NOT NULL
);

CREATE TABLE customer.forms (
    id integer NOT NULL,
    document_path character varying(255) NOT NULL,
    description character varying(255) NOT NULL,
    state character varying(32) NOT NULL,
    pestroutes_customer_id integer,
    pestroutes_unit_id integer,
    pestroutes_employee_id integer,
    pestroutes_template_id integer,
    pestroutes_date_signed timestamp with time zone,
    pestroutes_date_added timestamp with time zone,
    pestroutes_json jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE customer.note_types (
    id integer NOT NULL,
    external_ref_id integer,
    name character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE customer.note_types ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME customer.note_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE customer.notes (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    external_ref_id integer,
    account_id uuid NOT NULL,
    is_visible_to_customer boolean NOT NULL,
    is_visible_to_tech boolean NOT NULL,
    note_type_id integer NOT NULL,
    cancellation_reason_id integer,
    notes text,
    pestroutes_customer_id integer,
    pestroutes_created_by integer,
    pestroutes_cancellation_reason_id integer,
    pestroutes_note_type_id integer,
    pestroutes_note_type_name character varying(255),
    pestroutes_created_at timestamp(0) with time zone,
    pestroutes_updated_at timestamp(0) with time zone,
    pestroutes_json jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE customer.subscriptions (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    external_ref_id integer,
    is_active boolean NOT NULL,
    plan_id integer,
    initial_service_quote integer,
    initial_service_discount integer,
    initial_service_total integer,
    initial_status_id integer,
    recurring_charge integer,
    contract_value integer,
    annual_recurring_value integer,
    billing_frequency character varying(24),
    service_frequency character varying(24),
    days_til_follow_up_service integer,
    agreement_length_months integer,
    source character varying(255),
    cancellation_notes text,
    annual_recurring_services integer,
    renewal_frequency integer,
    max_monthly_charge double precision,
    initial_billing_date date,
    next_service_date date,
    last_service_date date,
    next_billing_date date,
    expiration_date date,
    custom_next_service_date date,
    appt_duration_in_mins integer,
    preferred_days_of_week character varying(24),
    preferred_start_time_of_day time without time zone,
    preferred_end_time_of_day time without time zone,
    addons jsonb,
    sold_by public.urn,
    sold_by_2 public.urn,
    sold_by_3 public.urn,
    cancelled_at timestamp(6) with time zone,
    pestroutes_customer_id integer,
    pestroutes_created_by integer,
    pestroutes_sold_by integer,
    pestroutes_sold_by_2 integer,
    pestroutes_sold_by_3 integer,
    pestroutes_service_type_id integer,
    pestroutes_source_id integer,
    pestroutes_last_appointment_id integer,
    pestroutes_preferred_tech_id integer,
    pestroutes_initial_appt_id integer,
    pestroutes_recurring_ticket jsonb,
    pestroutes_subscription_link character varying(255),
    pestroutes_created_at timestamp(0) with time zone,
    pestroutes_cancelled_at timestamp(0) with time zone,
    pestroutes_updated_at timestamp(0) with time zone,
    pestroutes_json jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    account_id uuid NOT NULL
);

COMMENT ON COLUMN customer.subscriptions.id IS '{"pestroutes_column_name": "subscriptionLink"}';

COMMENT ON COLUMN customer.subscriptions.external_ref_id IS '{"pestroutes_column_name": "subscriptionID"}';

COMMENT ON COLUMN customer.subscriptions.is_active IS '{"pestroutes_column_name": "active"}';

COMMENT ON COLUMN customer.subscriptions.initial_service_quote IS '{"pestroutes_column_name": "initialQuote"}';

COMMENT ON COLUMN customer.subscriptions.initial_service_discount IS '{"pestroutes_column_name": "initialDiscount"}';

COMMENT ON COLUMN customer.subscriptions.initial_service_total IS '{"pestroutes_column_name": "initialServiceTotal"}';

COMMENT ON COLUMN customer.subscriptions.initial_status_id IS '{"pestroutes_column_name": "initialStatus"}';

COMMENT ON COLUMN customer.subscriptions.recurring_charge IS '{"pestroutes_column_name": "recurringCharge"}';

COMMENT ON COLUMN customer.subscriptions.contract_value IS '{"pestroutes_column_name": "contractValue"}';

COMMENT ON COLUMN customer.subscriptions.annual_recurring_value IS '{"pestroutes_column_name": "annualRecurringValue"}';

COMMENT ON COLUMN customer.subscriptions.billing_frequency IS '{"pestroutes_column_name": "billingFrequency"}';

COMMENT ON COLUMN customer.subscriptions.service_frequency IS '{"pestroutes_column_name": "frequency"}';

COMMENT ON COLUMN customer.subscriptions.days_til_follow_up_service IS '{"pestroutes_column_name": "followupService"}';

COMMENT ON COLUMN customer.subscriptions.agreement_length_months IS '{"pestroutes_column_name": "agreementLength"}';

COMMENT ON COLUMN customer.subscriptions.source IS '{"pestroutes_column_name": "source"}';

COMMENT ON COLUMN customer.subscriptions.cancellation_notes IS '{"pestroutes_column_name": "cxlNotes"}';

COMMENT ON COLUMN customer.subscriptions.annual_recurring_services IS '{"pestroutes_column_name": "annualRecurringServices"}';

COMMENT ON COLUMN customer.subscriptions.renewal_frequency IS '{"pestroutes_column_name": "renewalFrequency"}';

COMMENT ON COLUMN customer.subscriptions.max_monthly_charge IS '{"pestroutes_column_name": "maxMonthlyCharge"}';

COMMENT ON COLUMN customer.subscriptions.initial_billing_date IS '{"pestroutes_column_name": "initialBillingDate"}';

COMMENT ON COLUMN customer.subscriptions.next_service_date IS '{"pestroutes_column_name": "nextService"}';

COMMENT ON COLUMN customer.subscriptions.last_service_date IS '{"pestroutes_column_name": "lastCompleted"}';

COMMENT ON COLUMN customer.subscriptions.next_billing_date IS '{"pestroutes_column_name": "nextBillingDate"}';

COMMENT ON COLUMN customer.subscriptions.expiration_date IS '{"pestroutes_column_name": "expirationDate"}';

COMMENT ON COLUMN customer.subscriptions.custom_next_service_date IS '{"pestroutes_column_name": "customDate"}';

COMMENT ON COLUMN customer.subscriptions.appt_duration_in_mins IS '{"pestroutes_column_name": "duration"}';

COMMENT ON COLUMN customer.subscriptions.preferred_days_of_week IS '{"pestroutes_column_name": "preferredDays"}';

COMMENT ON COLUMN customer.subscriptions.preferred_start_time_of_day IS '{"pestroutes_column_name": "preferredStart"}';

COMMENT ON COLUMN customer.subscriptions.preferred_end_time_of_day IS '{"pestroutes_column_name": "preferredEnd"}';

COMMENT ON COLUMN customer.subscriptions.addons IS '{"pestroutes_column_name": "addOns"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_customer_id IS '{"pestroutes_column_name": "customerID"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_created_by IS '{"pestroutes_column_name": "addedBy"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_sold_by IS '{"pestroutes_column_name": "soldBy"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_sold_by_2 IS '{"pestroutes_column_name": "soldBy2"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_sold_by_3 IS '{"pestroutes_column_name": "soldBy3"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_service_type_id IS '{"pestroutes_column_name": "serviceID"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_source_id IS '{"pestroutes_column_name": "sourceID"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_last_appointment_id IS '{"pestroutes_column_name": "lastAppointment"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_preferred_tech_id IS '{"pestroutes_column_name": "preferredTech"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_initial_appt_id IS '{"pestroutes_column_name": "initialAppointmentID"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_recurring_ticket IS '{"pestroutes_column_name": "recurringTicket"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_subscription_link IS '{"pestroutes_column_name": "subscriptionLink"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_created_at IS '{"pestroutes_column_name": "dateAdded"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_cancelled_at IS '{"pestroutes_column_name": "dateCancelled"}';

COMMENT ON COLUMN customer.subscriptions.pestroutes_updated_at IS '{"pestroutes_column_name": "dateUpdated"}';

CREATE TABLE dbe_test.notifications_sent_test (
    id bigint NOT NULL,
    notification_datetime timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    notification_type character(100),
    reference_id integer NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at timestamp(6) with time zone
);

COMMENT ON COLUMN dbe_test.notifications_sent_test.notification_datetime IS '{"field_desc":"When the notification was sent,"is_pii":true}';

COMMENT ON COLUMN dbe_test.notifications_sent_test.notification_type IS '{"field_desc":"This is a test comment.,"is_pii":true}';

COMMENT ON COLUMN dbe_test.notifications_sent_test.reference_id IS '{"pestroutes_column_name": "appointmentID","is_pii":true}';

COMMENT ON COLUMN dbe_test.notifications_sent_test.created_at IS '{"pestroutes_column_name": "TestID","is_pii":true}';

ALTER TABLE dbe_test.notifications_sent_test ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME dbe_test.notifications_sent_test_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE dbe_test.test_comments (
    test1 integer
);

CREATE TABLE dbe_test.test_key_tbl (
    id integer NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    name character varying(100),
    is_dropped boolean DEFAULT false NOT NULL
);

ALTER TABLE dbe_test.test_key_tbl ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME dbe_test.test_key_tbl_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE dbe_test.test_tbl (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE dbe_test.test_tbl ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME dbe_test.test_tbl_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.appointment_statuses (
    id integer NOT NULL,
    external_ref_id integer,
    name character varying(255) NOT NULL,
    description character varying(255),
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.appointment_statuses ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.appointment_statuses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.appointment_types (
    id integer NOT NULL,
    name character varying(255),
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.appointment_types ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.appointment_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.appointments (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    external_ref_id integer,
    account_id uuid NOT NULL,
    subscription_id uuid,
    appointment_type_id integer,
    route_id uuid,
    spot_id uuid,
    invoice_id uuid,
    date date NOT NULL,
    start_time time without time zone NOT NULL,
    end_time time without time zone NOT NULL,
    time_window character varying(255),
    call_ahead_in_mins integer,
    duration_in_mins integer NOT NULL,
    status_id integer NOT NULL,
    is_initial boolean NOT NULL,
    tech_time_in_at timestamp(6) with time zone,
    tech_time_out_at timestamp(6) with time zone,
    tech_check_in_at timestamp(6) with time zone,
    tech_check_out_at timestamp(6) with time zone,
    tech_check_in_lat double precision,
    tech_check_in_lng double precision,
    tech_check_out_lat double precision,
    tech_check_out_lng double precision,
    tech_check_in_geog_point public.geometry(Point,4326),
    tech_check_out_geog_point public.geometry(Point,4326),
    notes text,
    appt_notes text,
    office_notes text,
    do_interior integer NOT NULL,
    serviced_interior integer,
    wind_speed integer,
    wind_direction character varying(24),
    payment_method integer,
    amount_collected double precision,
    temperature double precision,
    assigned_tech uuid,
    serviced_by public.urn,
    completed_by public.urn,
    cancelled_by public.urn,
    completed_at timestamp(6) with time zone,
    cancelled_at timestamp(6) with time zone,
    pestroutes_customer_id integer,
    pestroutes_subscription_id integer,
    pestroutes_service_type_id integer,
    pestroutes_route_id integer,
    pestroutes_spot_id integer,
    pestroutes_invoice_id integer,
    pestroutes_status_id integer,
    pestroutes_created_by integer,
    pestroutes_assigned_tech integer,
    pestroutes_serviced_by integer,
    pestroutes_completed_by integer,
    pestroutes_cancelled_by integer,
    pestroutes_created_at timestamp(0) with time zone,
    pestroutes_completed_at timestamp(0) with time zone,
    pestroutes_cancelled_at timestamp(0) with time zone,
    pestroutes_updated_at timestamp(0) with time zone,
    pestroutes_json jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

COMMENT ON COLUMN field_operations.appointments.external_ref_id IS '{"pestroutes_column_name": "appointmentID"}';

COMMENT ON COLUMN field_operations.appointments.date IS '{"pestroutes_column_name": "date"}';

COMMENT ON COLUMN field_operations.appointments.start_time IS '{"pestroutes_column_name": "start"}';

COMMENT ON COLUMN field_operations.appointments.end_time IS '{"pestroutes_column_name": "end"}';

COMMENT ON COLUMN field_operations.appointments.time_window IS '{"pestroutes_column_name": "timeWindow"}';

COMMENT ON COLUMN field_operations.appointments.call_ahead_in_mins IS '{"pestroutes_column_name": "callAhead"}';

COMMENT ON COLUMN field_operations.appointments.duration_in_mins IS '{"pestroutes_column_name": "duration"}';

COMMENT ON COLUMN field_operations.appointments.is_initial IS '{"pestroutes_column_name": "isInitial"}';

COMMENT ON COLUMN field_operations.appointments.tech_time_in_at IS '{"pestroutes_column_name": "timeIn", "timezone_type": "office"}';

COMMENT ON COLUMN field_operations.appointments.tech_time_out_at IS '{"pestroutes_column_name": "timeOut", "timezone_type": "office"}';

COMMENT ON COLUMN field_operations.appointments.tech_check_in_at IS '{"pestroutes_column_name": "checkIn", "timezone_type": "server"}';

COMMENT ON COLUMN field_operations.appointments.tech_check_out_at IS '{"pestroutes_column_name": "checkOut", "timezone_type": "server"}';

COMMENT ON COLUMN field_operations.appointments.tech_check_in_lat IS '{"pestroutes_column_name": "latIn"}';

COMMENT ON COLUMN field_operations.appointments.tech_check_in_lng IS '{"pestroutes_column_name": "longIn"}';

COMMENT ON COLUMN field_operations.appointments.tech_check_out_lat IS '{"pestroutes_column_name": "latOut"}';

COMMENT ON COLUMN field_operations.appointments.tech_check_out_lng IS '{"pestroutes_column_name": "longOut"}';

COMMENT ON COLUMN field_operations.appointments.notes IS '{"pestroutes_column_name": "notes"}';

COMMENT ON COLUMN field_operations.appointments.appt_notes IS '{"pestroutes_column_name": "appointmentNotes"}';

COMMENT ON COLUMN field_operations.appointments.office_notes IS '{"pestroutes_column_name": "officeNotes"}';

COMMENT ON COLUMN field_operations.appointments.do_interior IS '{"pestroutes_column_name": "doInterior"}';

COMMENT ON COLUMN field_operations.appointments.serviced_interior IS '{"pestroutes_column_name": "servicedInterior"}';

COMMENT ON COLUMN field_operations.appointments.wind_speed IS '{"pestroutes_column_name": "windSpeed"}';

COMMENT ON COLUMN field_operations.appointments.wind_direction IS '{"pestroutes_column_name": "windDirection"}';

COMMENT ON COLUMN field_operations.appointments.payment_method IS '{"pestroutes_column_name": "paymentMethod"}';

COMMENT ON COLUMN field_operations.appointments.amount_collected IS '{"pestroutes_column_name": "amountCollected"}';

COMMENT ON COLUMN field_operations.appointments.temperature IS '{"pestroutes_column_name": "temperature"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_customer_id IS '{"pestroutes_column_name": "customerID"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_subscription_id IS '{"pestroutes_column_name": "subscriptionID"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_service_type_id IS '{"pestroutes_column_name": "type"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_route_id IS '{"pestroutes_column_name": "routeID"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_spot_id IS '{"pestroutes_column_name": "spotID"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_invoice_id IS '{"pestroutes_column_name": "ticketID"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_status_id IS '{"pestroutes_column_name": "status"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_created_by IS '{"pestroutes_column_name": "employeeID"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_assigned_tech IS '{"pestroutes_column_name": "assignedTech"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_serviced_by IS '{"pestroutes_column_name": "servicedBy"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_completed_by IS '{"pestroutes_column_name": "completedBy"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_cancelled_by IS '{"pestroutes_column_name": "cancelledBy"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_created_at IS '{"pestroutes_column_name": "dateAdded", "timezone_type": "server"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_completed_at IS '{"pestroutes_column_name": "dateCompleted", "timezone_type": "server"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_cancelled_at IS '{"pestroutes_column_name": "dateCancelled", "timezone_type": "server"}';

COMMENT ON COLUMN field_operations.appointments.pestroutes_updated_at IS '{"pestroutes_column_name": "dateUpdated", "timezone_type": "server"}';

CREATE TABLE field_operations.areas (
    id integer NOT NULL,
    external_ref_id integer,
    market_id integer,
    name character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    timezone character varying(255),
    license_number character varying(255),
    address character varying(255),
    city character varying(255),
    state character varying(255),
    zip character varying(255),
    phone character varying(255),
    email character varying(255),
    website character varying(255),
    caution_statements text
);

ALTER TABLE field_operations.areas ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.areas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.aro_failed_jobs (
    id integer NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text,
    queue text,
    payload text,
    exception text,
    failed_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.aro_failed_jobs ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.aro_failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.aro_users (
    id integer NOT NULL,
    username character varying(32) NOT NULL,
    password character varying(128) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE field_operations.customer_property_details (
    id integer NOT NULL,
    customer_id integer,
    land_sqft double precision,
    building_sqft double precision,
    living_sqft double precision,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.customer_property_details ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME field_operations.customer_property_details_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.failure_notification_recipients (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    phone character varying(20) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.failure_notification_recipients ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.failure_notification_recipients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.markets (
    id integer NOT NULL,
    region_id integer,
    name character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.markets ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.markets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.monthly_financial_reports (
    id integer NOT NULL,
    year integer NOT NULL,
    month character varying(3) NOT NULL,
    amount double precision NOT NULL,
    cost_center_id character varying(32),
    cost_center character varying(128),
    ledger_account_type character varying(16) NOT NULL,
    ledger_account_id character varying(32) NOT NULL,
    ledger_account character varying(128) NOT NULL,
    spend_category_id character varying(32),
    spend_category character varying(128),
    revenue_category_id character varying(32),
    revenue_category character varying(128),
    service_center_id character varying(32),
    service_center character varying(128),
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.monthly_financial_reports ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME field_operations.monthly_financial_reports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.notification_recipient_type (
    id integer NOT NULL,
    type_id integer NOT NULL,
    notification_recipient_id integer NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    notification_channel field_operations.notification_channels DEFAULT 'email'::field_operations.notification_channels NOT NULL,
    days_out integer
);

ALTER TABLE field_operations.notification_recipient_type ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME field_operations.notification_recipient_type_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.notification_recipients (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    phone character varying(30),
    email character varying(255),
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.notification_recipients ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME field_operations.notification_recipients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.notification_types (
    id integer NOT NULL,
    type character varying(100) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.notification_types ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME field_operations.notification_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.office_days_participants (
    schedule_id integer NOT NULL,
    employee_id integer NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE field_operations.office_days_schedule (
    id integer NOT NULL,
    title character varying(100) NOT NULL,
    description character varying(500),
    office_id integer NOT NULL,
    start_date date NOT NULL,
    end_date date,
    start_time time without time zone NOT NULL,
    end_time time without time zone NOT NULL,
    time_zone character varying(32) NOT NULL,
    location jsonb,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at timestamp(6) with time zone,
    "interval" character varying(16) DEFAULT 'weekly'::character varying NOT NULL,
    occurrence character varying(255) DEFAULT NULL::character varying,
    week integer,
    meeting_link character varying(200),
    address jsonb,
    event_type character varying(32) DEFAULT 'meeting'::character varying NOT NULL,
    end_after character varying(16) DEFAULT 'date'::character varying NOT NULL,
    occurrences integer,
    repeat_every integer
);

COMMENT ON COLUMN field_operations.office_days_schedule.repeat_every IS 'Repeat every N days/weeks/months/years';

ALTER TABLE field_operations.office_days_schedule ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.office_days_schedule_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.office_days_schedule_overrides (
    id integer NOT NULL,
    schedule_id integer NOT NULL,
    title character varying(128) NOT NULL,
    description text,
    is_canceled boolean,
    start_time time without time zone NOT NULL,
    end_time time without time zone NOT NULL,
    time_zone character varying(32) NOT NULL,
    location jsonb,
    date date NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at timestamp(6) with time zone,
    meeting_link text,
    address jsonb
);

ALTER TABLE field_operations.office_days_schedule_overrides ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.office_days_schedule_overrides_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.optimization_states (
    id integer NOT NULL,
    state jsonb,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at timestamp(6) with time zone,
    office jsonb,
    stats jsonb,
    as_of_date date NOT NULL,
    previous_state_id integer,
    metrics jsonb,
    rules jsonb,
    weather_forecast jsonb,
    status character varying(32)
);

ALTER TABLE field_operations.optimization_states ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.optimization_states_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.regions (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.regions ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.regions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.route_actual_stats (
    id integer NOT NULL,
    route_id integer NOT NULL,
    as_of_date date NOT NULL,
    service_pro_id integer NOT NULL,
    stats jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.route_actual_stats ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.route_actual_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.route_details (
    id integer NOT NULL,
    optimization_state_id integer NOT NULL,
    route_id integer NOT NULL,
    details jsonb,
    schedule jsonb,
    service_pro jsonb,
    metrics jsonb,
    stats jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.route_details ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME field_operations.route_details_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.route_geometries (
    id integer NOT NULL,
    optimization_state_id integer NOT NULL,
    route_id integer NOT NULL,
    geometry text NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.route_geometries ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME field_operations.route_geometries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.route_groups (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

COMMENT ON COLUMN field_operations.route_groups.name IS '{"pestroutes_column_name": "groupTitle"}';

ALTER TABLE field_operations.route_groups ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.route_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.route_stats (
    id integer NOT NULL,
    optimization_state_id integer NOT NULL,
    route_id integer NOT NULL,
    stats jsonb,
    service_pro jsonb
);

ALTER TABLE field_operations.route_stats ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.route_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.route_templates (
    id integer NOT NULL,
    external_ref_id integer,
    name character varying(255) NOT NULL,
    is_active boolean NOT NULL,
    pestroutes_json jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.route_templates ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME field_operations.route_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.routes (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    external_ref_id integer,
    name character varying(255),
    route_group_id integer NOT NULL,
    route_template_id integer,
    date date,
    user_assigned_to uuid,
    pestroutes_created_by integer,
    pestroutes_route_template_id integer,
    pestroutes_route_group_title character varying(255),
    pestroutes_assigned_tech_employee_id integer,
    pestroutes_created_at timestamp(0) with time zone,
    pestroutes_updated_at timestamp(0) with time zone,
    pestroutes_deleted_at timestamp(0) with time zone,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    area_id integer NOT NULL,
    pestroutes_json jsonb
);

COMMENT ON COLUMN field_operations.routes.external_ref_id IS '{"pestroutes_column_name": "routeID"}';

COMMENT ON COLUMN field_operations.routes.name IS '{"pestroutes_column_name": "name"}';

COMMENT ON COLUMN field_operations.routes.route_template_id IS '{"pestroutes_column_name": "templateID"}';

COMMENT ON COLUMN field_operations.routes.date IS '{"pestroutes_column_name": "date"}';

COMMENT ON COLUMN field_operations.routes.pestroutes_created_by IS '{"pestroutes_column_name": "addedBy"}';

COMMENT ON COLUMN field_operations.routes.pestroutes_route_template_id IS '{"pestroutes_column_name": "templateID"}';

COMMENT ON COLUMN field_operations.routes.pestroutes_route_group_title IS '{"pestroutes_column_name": "groupTitle"}';

COMMENT ON COLUMN field_operations.routes.pestroutes_assigned_tech_employee_id IS '{"pestroutes_column_name": "assignedTech"}';

COMMENT ON COLUMN field_operations.routes.pestroutes_created_at IS '{"pestroutes_column_name": "dateAdded, "timezone_type": "server"}';

COMMENT ON COLUMN field_operations.routes.pestroutes_updated_at IS '{"pestroutes_column_name": "dateUpdated, "timezone_type": "server"}';

CREATE TABLE field_operations.scheduled_route_details (
    id integer NOT NULL,
    scheduling_state_id integer NOT NULL,
    route_id integer NOT NULL,
    details jsonb,
    pending_services jsonb,
    appointments jsonb,
    service_pro jsonb,
    metrics jsonb,
    stats jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.scheduled_route_details ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME field_operations.scheduled_route_details_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.scheduling_states (
    id integer NOT NULL,
    as_of_date date NOT NULL,
    office_id integer NOT NULL,
    pending_services jsonb,
    scheduled_routes jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    stats jsonb
);

ALTER TABLE field_operations.scheduling_states ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.scheduling_states_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.service_actual_stats (
    id integer NOT NULL,
    office_id integer NOT NULL,
    as_of_date date NOT NULL,
    stats jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.service_actual_stats ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME field_operations.service_actual_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.service_types (
    id integer NOT NULL,
    external_ref_id integer NOT NULL,
    name text NOT NULL,
    plan_id integer,
    appointment_type_id integer,
    pestroutes_json jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.service_types ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME field_operations.service_types_new_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.serviced_route_details (
    id integer NOT NULL,
    treatment_state_id integer NOT NULL,
    route_id integer NOT NULL,
    service_pro jsonb,
    stats jsonb,
    schedule jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.serviced_route_details ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME field_operations.serviced_route_details_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.treatment_states (
    id integer NOT NULL,
    as_of_date date NOT NULL,
    office_id integer NOT NULL,
    stats jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE field_operations.treatment_states ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME field_operations.treatment_states_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE field_operations.user_areas (
    user_id uuid NOT NULL,
    area_id integer NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE VIEW guru.area AS
 SELECT ecosure_office_branches.id AS area_id,
    ecosure_office_branches.name,
    ecosure_office_branches.branch_manager_id AS manager_id,
    ecosure_office_branches.timezone,
    ecosure_office_branches.ext,
    ecosure_office_branches.branch_phone,
    ecosure_office_branches.address,
    ecosure_office_branches.street_address_1 AS address_line1,
    ecosure_office_branches.street_address_2 AS address_line2,
    ecosure_office_branches.city,
    ecosure_office_branches.state,
    ecosure_office_branches.zip,
    ecosure_office_branches.office_email,
    ecosure_office_branches.lat AS latitude,
    ecosure_office_branches.long AS longitude,
    ecosure_office_branches.initial_service,
    ecosure_office_branches.quarterly_service,
    (ecosure_office_branches.is_price)::boolean AS is_price,
    ecosure_office_branches.timezone_id,
    (ecosure_office_branches.visible)::boolean AS active,
    ecosure_office_branches.vantiv_accountid,
    ecosure_office_branches.vantive_accounttoken,
    ecosure_office_branches.vantive_acceptorid,
    ecosure_office_branches.pestroutes_alternative,
    ecosure_office_branches.housing_licensing_branch_id,
    ecosure_office_branches.approx_reps_needed,
    ecosure_office_branches.approx_reps_needed_pre,
    ecosure_office_branches.approx_reps_needed_post,
    (ecosure_office_branches.is_saturday)::boolean AS is_saturday,
    (ecosure_office_branches.is_sunday)::boolean AS is_sunday,
    ecosure_office_branches.holiday_list,
    ecosure_office_branches.hellosignid,
    ecosure_office_branches.docusign_id,
    ecosure_office_branches.template_name,
    ecosure_office_branches.template_year,
    ecosure_office_branches.license_number,
    (ecosure_office_branches.is_in_pestroutes)::boolean AS is_in_pestroutes,
    (ecosure_office_branches.is_monthly_billing_option)::boolean AS is_monthly_billing_option,
    (ecosure_office_branches.require_tax_rate)::boolean AS require_tax_rate,
    ecosure_office_branches.require_tax_rate_state,
    ecosure_office_branches.pestroutes_office_id,
    ecosure_office_branches.spt_qualified_address_max_score,
    1 AS company_id,
    false AS deleted,
    ecosure_office_branches.created_at,
    ecosure_office_branches.updated_at
   FROM alterra_production.ecosure_office_branches;

CREATE VIEW guru.contact AS
 SELECT ecosure_contacts.id AS contact_id,
    ecosure_contacts.user_id,
    ecosure_contacts.lead_id,
    ecosure_contacts.first_name,
    ecosure_contacts.last_name,
    ecosure_contacts.preferred_name,
    ecosure_contacts.mobile,
    ecosure_contacts.mobile2,
    ecosure_contacts.emergency_contact_name,
    ecosure_contacts.emergency_phone_number,
    ecosure_contacts.alternate_email,
    ecosure_contacts.address1,
    ecosure_contacts.address2,
    ecosure_contacts.city,
    ecosure_contacts.state,
    ecosure_contacts.country,
    ecosure_contacts.zip,
    ecosure_contacts.no_of_years,
    ecosure_contacts.is_different_address,
    ecosure_contacts.permanent_address,
    ecosure_contacts.permanent_city,
    ecosure_contacts.permanent_state,
    ecosure_contacts.permanent_country,
    ecosure_contacts.permanent_zip,
    ecosure_contacts.experience,
    ecosure_contacts.experience_in_years,
    ecosure_contacts.industry_coming_from,
    ecosure_contacts.number_of_accounts_previous_company,
    ecosure_contacts.school_id,
    ecosure_contacts.experience_in_industry,
    (ecosure_contacts.participated_in_a_company_meeting)::boolean AS participated_in_a_company_meeting,
    (ecosure_contacts.made_travel_meeting)::boolean AS made_travel_meeting,
    (ecosure_contacts.participated_in_company_activity)::boolean AS participated_in_company_activity,
    (ecosure_contacts.visited_company_building)::boolean AS visited_company_building,
    (ecosure_contacts.attended_3_or_more_training_meetings)::boolean AS attended_3_or_more_training_meetings,
    (ecosure_contacts.training_on_doors)::boolean AS training_on_doors,
    (ecosure_contacts.met_with_owners)::boolean AS met_with_owners,
    ecosure_contacts.personality_test_score,
    ecosure_contacts.personality_test_id,
    ecosure_contacts.start_date,
    ecosure_contacts.end_date,
    ecosure_contacts.is_subscribed,
    ecosure_contacts.up_front_pay,
    ecosure_contacts.rent_situation,
    ecosure_contacts.created,
    ecosure_contacts.modified,
    (NULLIF((ecosure_contacts.has_car)::text, ''::text))::boolean AS has_car,
    (NULLIF((ecosure_contacts.has_segway)::text, ''::text))::boolean AS has_segway,
    ecosure_contacts.expected_arrival_date,
    ecosure_contacts.polo_shirt_size,
    ecosure_contacts.hat_size,
    ecosure_contacts.jacket_size,
    ecosure_contacts.waist_size,
    ecosure_contacts.shoe_size,
    ecosure_contacts.marital_status,
    ecosure_contacts.spouse_name,
    ecosure_contacts.spouse_last_name,
    ecosure_contacts.dob,
    ecosure_contacts.age,
    ecosure_contacts.place_of_birth,
    ecosure_contacts.birth_state,
    ecosure_contacts.other_birth_state,
    ecosure_contacts.ethnicity,
    ecosure_contacts.gender,
    ecosure_contacts.veteran,
    ecosure_contacts.height,
    ecosure_contacts.weight,
    ecosure_contacts.hair_color,
    ecosure_contacts.eye_color,
    ecosure_contacts.drivers_license_number,
    ecosure_contacts.drivers_license_expiration_date,
    ecosure_contacts.state_issued,
    ecosure_contacts.ss,
    ecosure_contacts.llc_name,
    ecosure_contacts.ein_number,
    ecosure_contacts.sales_license_number,
    ecosure_contacts.sales_state_issued,
    ecosure_contacts.expiration_date,
    ecosure_contacts.state_license_number,
    ecosure_contacts.state_license_expiration_date,
    ecosure_contacts.previous_sales_company,
    ecosure_contacts.number_of_accounts,
        CASE ecosure_contacts.has_visible_markings
            WHEN 'yes'::text THEN true
            WHEN 'no'::text THEN false
            ELSE NULL::boolean
        END AS has_visible_markings,
    ecosure_contacts.visible_markings_reason,
        CASE ecosure_contacts.is_us_citizen
            WHEN 'yes'::text THEN true
            WHEN 'no'::text THEN false
            ELSE NULL::boolean
        END AS is_us_citizen,
    (ecosure_contacts.is_switchover)::boolean AS is_switchover,
    ecosure_contacts.years_sold,
    ecosure_contacts.created_at,
    ecosure_contacts.updated_at
   FROM alterra_production.ecosure_contacts;

CREATE VIEW guru.housing_team AS
 SELECT ecosure_housing_tbl_team.id AS housing_team_id,
    ecosure_housing_tbl_team.team_id,
    ecosure_housing_tbl_team.team_name AS name,
    ecosure_housing_tbl_team.branch_id AS area_id,
    ecosure_housing_tbl_team.primary_regional_id,
    ecosure_housing_tbl_team.primary_ram_id,
    ecosure_housing_tbl_team.reps_needed,
    ecosure_housing_tbl_team.sps_needed,
    ecosure_housing_tbl_team.team_start_date,
    (ecosure_housing_tbl_team.is_deleted)::boolean AS deleted,
    ecosure_housing_tbl_team.season_id,
    ecosure_housing_tbl_team.recruiting_season AS recruiting_season_id,
    ecosure_housing_tbl_team.created_at,
    ecosure_housing_tbl_team.updated_at
   FROM alterra_production.ecosure_housing_tbl_team;

CREATE VIEW guru.housing_team_slot AS
 SELECT ecosure_housing_team_slots.id AS housing_team_slot_id,
    ecosure_housing_team_slots.reg_div_id,
    ecosure_housing_team_slots.tbl_team_id AS housing_team_id,
    ecosure_housing_team_slots.slot_position,
        CASE ecosure_housing_team_slots.is_filled
            WHEN '1'::text THEN true
            WHEN '0'::text THEN false
            ELSE NULL::boolean
        END AS filled,
    ecosure_housing_team_slots.rep_id,
        CASE ecosure_housing_team_slots.is_team_lead
            WHEN '1'::text THEN true
            WHEN '0'::text THEN false
            ELSE NULL::boolean
        END AS team_lead,
    ecosure_housing_team_slots.other_reg_user_id,
    ecosure_housing_team_slots."timestamp",
    ecosure_housing_team_slots.created_at,
    ecosure_housing_team_slots.updated_at
   FROM alterra_production.ecosure_housing_team_slots;

CREATE VIEW guru.recruit AS
 SELECT ecosure_recruits.id AS recruit_id,
    ecosure_recruits.company_id,
    ecosure_recruits.user_id,
    ecosure_recruits.lead_id,
    ecosure_recruits.type,
    ecosure_recruits.status,
    ecosure_recruits.follow_up_date,
    ecosure_recruits.recruiting_season_id,
    (ecosure_recruits.is_approved)::boolean AS is_approved,
    (ecosure_recruits.is_signed)::boolean AS signed,
    (ecosure_recruits.converted_from_lead)::boolean AS converted_from_lead,
    ecosure_recruits.date_signed,
    ecosure_recruits.signed_by_id_back,
    ecosure_recruits.signed_by_id,
    ecosure_recruits.last_season_status,
    ecosure_recruits.school_id,
    ecosure_recruits.other_school_name,
    ecosure_recruits.start_date,
    ecosure_recruits.end_date,
    ecosure_recruits.created,
    ecosure_recruits.modified,
    ecosure_recruits.deleted,
    ecosure_recruits.last_contract_added_at,
    (ecosure_recruits.is_sent)::boolean AS is_sent,
    ecosure_recruits.note,
    (ecosure_recruits.is_complete)::boolean AS is_complete,
    (ecosure_recruits.is_manually_signed)::boolean AS is_manually_signed,
    ecosure_recruits.sales_season_id,
    ecosure_recruits.experience,
    (ecosure_recruits.is_manually_unsigned)::boolean AS is_manually_unsigned,
    ecosure_recruits.face_to_face,
    ecosure_recruits.temp_team_id,
    ecosure_recruits.team_slot_id AS housing_team_slot_id,
    ecosure_recruits.trainer,
    ecosure_recruits.primary_regional_id,
    ecosure_recruits.assigned_reps_timestamp,
    ecosure_recruits.preseason_temp_team_id,
    ecosure_recruits.preseason_team_slot_id AS housing_team_slot_id_preseason,
    ecosure_recruits.preseason_trainer,
    ecosure_recruits.postseason_temp_team_id AS housing_team_slot_id_postseason,
    ecosure_recruits.postseason_team_slot_id,
    ecosure_recruits.postseason_trainer,
    ecosure_recruits.kickstart_gear,
    ecosure_recruits.label_id,
    (ecosure_recruits.is_updated_knockingdates)::boolean AS is_updated_knockingdates,
    ecosure_recruits.hide_contract,
    ecosure_recruits.ssn,
    ecosure_recruits.created_at,
    ecosure_recruits.updated_at
   FROM alterra_production.ecosure_recruits;

CREATE VIEW guru.recruiting_season AS
 SELECT ecosure_recruiting_seasons.id AS recruiting_season_id,
    ecosure_recruiting_seasons.name,
    ecosure_recruiting_seasons.start_date,
    ecosure_recruiting_seasons.end_date,
    (ecosure_recruiting_seasons.is_current)::boolean AS is_current,
    ecosure_recruiting_seasons.aptive_pay_year,
    (ecosure_recruiting_seasons.is_sales)::boolean AS is_sales,
    ecosure_recruiting_seasons.goal,
    ecosure_recruiting_seasons.created,
    ecosure_recruiting_seasons.modified,
    ecosure_recruiting_seasons.sales_season_id,
    ecosure_recruiting_seasons.graph_end_date,
    ecosure_recruiting_seasons.created_at,
    ecosure_recruiting_seasons.updated_at
   FROM alterra_production.ecosure_recruiting_seasons;

CREATE VIEW guru.sales_season AS
 SELECT ecosure_seasons.id AS sales_season_id,
    ecosure_seasons.company_id,
    ecosure_seasons.name,
    ecosure_seasons.start_date,
    ecosure_seasons.end_date,
    ecosure_seasons.summer_start_date,
    ecosure_seasons.summer_end_date,
    ((ecosure_seasons.is_current)::integer)::boolean AS is_current,
    ((ecosure_seasons.restrict_viewing)::integer)::boolean AS restrict_viewing,
    ecosure_seasons.sales_goal,
    ecosure_seasons.created,
    ecosure_seasons.modified,
    ecosure_seasons.created_at,
    ecosure_seasons.updated_at
   FROM alterra_production.ecosure_seasons;

CREATE VIEW guru.scout AS
 SELECT ecosure_scouts.id AS scout_id,
    ecosure_scouts.company_id,
    ecosure_scouts.scout_group_id,
    ecosure_scouts.user_id,
    ecosure_scouts.parent_scout_id,
    ecosure_scouts.recruiting_season_id,
    ecosure_scouts.can_sign_leads_without_approval,
    ecosure_scouts.goal,
    ecosure_scouts.region_goal,
    ecosure_scouts.tasks,
    ecosure_scouts.created,
    ecosure_scouts.modified,
    (ecosure_scouts.is_deleted)::boolean AS deleted,
    ecosure_scouts.hierarchy_order,
    (ecosure_scouts.area_management)::boolean AS area_management,
    ecosure_scouts.created_at,
    ecosure_scouts.updated_at
   FROM alterra_production.ecosure_scouts;

CREATE VIEW guru.team AS
 SELECT ecosure_offices.id AS team_id,
    ecosure_offices.name,
    ecosure_offices.sales_goal,
    ecosure_offices.officeid,
    ecosure_offices.manager_id,
    ecosure_offices.office_branch_id AS area_id,
    ecosure_offices.avatar,
    ecosure_offices.map_grid_id,
    (ecosure_offices.use_office_map_area_restrictions)::boolean AS use_office_map_area_restrictions,
    ecosure_offices.timezone_id,
    ecosure_offices.latitude,
    ecosure_offices.longitude,
    ecosure_offices.housing_need,
    (ecosure_offices.is_pre_season)::boolean AS is_pre_season,
    (ecosure_offices.is_post_season)::boolean AS is_post_season,
    ecosure_offices.tl_id,
    ecosure_offices.rm_id,
    ecosure_offices.bm_id,
    ecosure_offices.no_of_wives,
    ecosure_offices.divisor,
    ecosure_offices.created,
    ecosure_offices.modified,
    (ecosure_offices.deleted)::boolean AS deleted,
    (ecosure_offices.visible)::boolean AS active,
    ecosure_offices.ss_blue_pin_max_score,
    ecosure_offices.ss_cells_max_average_score,
    ecosure_offices.created_at,
    ecosure_offices.updated_at
   FROM alterra_production.ecosure_offices;

CREATE VIEW guru."user" AS
 SELECT ecosure_users.id AS user_id,
    ecosure_users.email,
    ecosure_users.password,
    (ecosure_users.active)::boolean AS active,
    (ecosure_users.tournament_active)::boolean AS tournament_active,
    (ecosure_users.knock_active)::boolean AS knock_active,
    (NULLIF((ecosure_users.offseason_access)::text, ''::text))::boolean AS offseason_access,
    (ecosure_users.old_offseason_access)::boolean AS old_offseason_access,
    (ecosure_users.recruiting_access)::boolean AS recruiting_access,
    ecosure_users.group_id,
    ecosure_users.office_id AS team_id,
    ecosure_users.recruiting_office_id,
    ecosure_users.temp_office_id,
    ecosure_users.primary_regional_id,
    ecosure_users.team_slot_id,
    ecosure_users.housing_divisional_id,
    ecosure_users.assigned_reps_timestamp,
    ecosure_users.preseason_temp_office_id,
    ecosure_users.preseason_team_slot_id,
    ecosure_users.postseason_temp_office_id,
    ecosure_users.postseason_team_slot_id,
    ecosure_users.regional_manager_id_old,
    ecosure_users.regional_manager_id,
    ecosure_users.repid,
    ecosure_users.pestroutesid,
    ecosure_users.pay_scale_id,
    ecosure_users.bi_monthly_pay_overide,
    ecosure_users.override_rent,
    ecosure_users.account_percentage_override,
    ecosure_users.avatar,
    ecosure_users.last_login_date,
    ecosure_users.can_view_office,
    ecosure_users.sales_goal,
    ecosure_users.region_sales_goal,
    ecosure_users.created,
    ecosure_users.modified,
    ecosure_users.date_active,
    (ecosure_users.deleted)::boolean AS deleted,
    (ecosure_users.master_tournament_active)::boolean AS master_tournament_active,
    (ecosure_users.per_rep_average)::boolean AS per_rep_average,
    ecosure_users.subscription_sent,
    (ecosure_users.unsubscribe)::boolean AS unsubscribe,
    ecosure_users.payroll_type,
    ecosure_users.rent_type,
    ecosure_users.fb_username,
    ecosure_users.regional_scout_id,
    ecosure_users.servsuit_branch_id,
    ecosure_users.payrollfilenumber,
    ecosure_users.allowednoproofpercentage,
    ecosure_users.parent_id,
    ecosure_users.daysoff,
    ecosure_users.tardies,
    ecosure_users.hierarchy_order,
    ecosure_users.license_number,
    ecosure_users.discount_code,
    ecosure_users.do_not_hire,
    ecosure_users.do_not_hire_reason,
    ecosure_users.branch_access,
    ecosure_users.passport_img,
    ecosure_users.dl_img,
    ecosure_users.ssn_img,
    ecosure_users.is_profile_img_lock,
    (ecosure_users.is_test_user)::boolean AS is_test_user,
    ecosure_users.workday_id,
        CASE ecosure_users.is_workday_id_edited
            WHEN 'yes'::text THEN true
            WHEN 'no'::text THEN false
            ELSE NULL::boolean
        END AS is_workday_id_edited,
    ecosure_users.sign_img,
    ecosure_users.super_admin,
    ecosure_users.password_hash_sha,
    ecosure_users.user_salt,
    ecosure_users.password_cost,
    ecosure_users.company_id,
    ecosure_users.dealer_id,
    (ecosure_users.app_access)::boolean AS app_access,
    ecosure_users.is_unlocak_email_send,
    ecosure_users.can_edit_branch_price,
    ecosure_users.kickoff_sms_sent,
    ecosure_users.kickoff_email_sent,
    ecosure_users.created_at,
    ecosure_users.updated_at,
    (ecosure_users.training_complete)::boolean AS training_complete,
    (ecosure_users.hr_docs_complete)::boolean AS hr_docs_complete,
    ecosure_users.wotc_survey_completed
   FROM alterra_production.ecosure_users;

CREATE TABLE licensing.counties (
    id integer NOT NULL,
    name text NOT NULL,
    state_id integer NOT NULL,
    is_license_required boolean DEFAULT false NOT NULL,
    boundary public.geometry(Geometry,4326)
);

ALTER TABLE licensing.counties ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME licensing.counties_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE licensing.municipalities (
    id integer NOT NULL,
    name text NOT NULL,
    state_id integer NOT NULL,
    is_license_required boolean DEFAULT false NOT NULL,
    boundary public.geometry(Geometry,4326)
);

ALTER TABLE licensing.municipalities ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME licensing.municipalities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE licensing.states (
    id integer NOT NULL,
    name text NOT NULL,
    abbreviation character(2) NOT NULL,
    is_license_required boolean DEFAULT false NOT NULL,
    boundary public.geometry(Geometry,4326)
);

ALTER TABLE licensing.states ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME licensing.states_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE licensing.user_county_licenses (
    user_id integer NOT NULL,
    county_id integer NOT NULL,
    license_number text,
    effective_from_date date,
    effective_to_date date
);

CREATE TABLE licensing.user_denials_log (
    id integer NOT NULL,
    user_id integer NOT NULL,
    municipality_id integer,
    county_id integer,
    state_id integer,
    created_at timestamp(6) with time zone
);

ALTER TABLE licensing.user_denials_log ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME licensing.user_denials_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE licensing.user_municipality_licenses (
    user_id integer NOT NULL,
    municipality_id integer NOT NULL,
    license_number text,
    effective_from_date date,
    effective_to_date date
);

CREATE TABLE licensing.user_state_licenses (
    user_id integer NOT NULL,
    state_id integer NOT NULL,
    license_number text,
    effective_from_date date,
    effective_to_date date
);

CREATE TABLE notifications.cache (
    id integer NOT NULL,
    name character varying(256) NOT NULL,
    status character(1) DEFAULT 'A'::bpchar NOT NULL,
    content text,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE notifications.cache ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME notifications.cache_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE notifications.headshot_paths (
    id integer NOT NULL,
    user_id character varying(100) NOT NULL,
    user_type public.user_types NOT NULL,
    original_path character varying(255) NOT NULL,
    path character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    meta jsonb
);

ALTER TABLE notifications.headshot_paths ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME notifications.headshot_paths_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE notifications.incoming_mms_messages (
    id bigint NOT NULL,
    twilio_message_id character varying(64) NOT NULL,
    twilio_media_id character varying(64) NOT NULL,
    aws_region character varying(32) DEFAULT ''::character varying NOT NULL,
    aws_bucket character varying(64) DEFAULT ''::character varying NOT NULL,
    aws_file_path character varying(128) DEFAULT ''::character varying NOT NULL,
    temporary_url character varying(255),
    temporary_url_expiration timestamp(6) with time zone,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE notifications.incoming_mms_messages ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME notifications.incoming_mms_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE notifications.logs (
    id bigint NOT NULL,
    type character varying(64) NOT NULL,
    queue_name character varying(128) NOT NULL,
    method notifications.methods NOT NULL,
    reference_id bigint NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE notifications.logs_email (
    id bigint NOT NULL,
    log_id bigint,
    started_at timestamp(6) with time zone,
    sent_at timestamp(6) with time zone,
    skipped_at timestamp(6) with time zone,
    delayed_at timestamp(6) with time zone,
    failed_at timestamp(6) with time zone,
    one_time_delayed_at timestamp(6) with time zone,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    queued_at timestamp(6) with time zone
);

COMMENT ON COLUMN notifications.logs_email.queued_at IS 'Queued At';

ALTER TABLE notifications.logs_email ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME notifications.logs_email_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

ALTER TABLE notifications.logs ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME notifications.logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE notifications.notifications_sent (
    id bigint NOT NULL,
    notification_datetime timestamp(6) with time zone,
    notification_type notifications.notification_types,
    reference_id integer NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at timestamp(6) with time zone,
    status public.notification_statuses,
    attempt smallint
);

COMMENT ON COLUMN notifications.notifications_sent.notification_datetime IS 'When the notification was sent';

COMMENT ON COLUMN notifications.notifications_sent.reference_id IS 'The id associated with the notification. Will be different based on the notification type.';

CREATE TABLE notifications.notifications_sent_batches (
    id bigint NOT NULL,
    batch_id character varying(255),
    batch_type notifications.batch_type NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE notifications.notifications_sent_batches ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME notifications.notifications_sent_batches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

ALTER TABLE notifications.notifications_sent ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME notifications.notifications_sent_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE notifications.sms_messages (
    id integer NOT NULL,
    sid character varying,
    reference_id integer,
    reference_type character varying,
    sender_id integer,
    sender_type character varying,
    sms_datetime timestamp(6) with time zone,
    message text,
    "from" character varying,
    "to" character varying,
    status character varying,
    meta jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

COMMENT ON COLUMN notifications.sms_messages.sid IS 'Service message id';

ALTER TABLE notifications.sms_messages ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME notifications.sms_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE notifications.sms_messages_media (
    id integer NOT NULL,
    sms_message_id integer NOT NULL,
    path character varying,
    meta jsonb,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE notifications.sms_messages_media ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME notifications.sms_messages_media_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE organization.api_accounts (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    name character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

COMMENT ON COLUMN organization.api_accounts.id IS 'Same UUID as the Entity created in Fusion Auth';

CREATE VIEW organization.areas AS
 SELECT ecosure_office_branches.id,
    ecosure_office_branches.name,
    (ecosure_office_branches.visible)::boolean AS is_active
   FROM alterra_production.ecosure_office_branches;

CREATE TABLE organization.dealers (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE organization.dealers ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME organization.dealers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE organization.user_dealers (
    user_id uuid NOT NULL,
    dealer_id integer NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE organization.users (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    username character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    fusionauth_id uuid,
    fusionauth_access_token text,
    fusionauth_refresh_token text,
    external_ref_id character varying(255),
    name character varying(255),
    fusionauth_groups jsonb,
    phone_number character varying(24),
    is_active boolean DEFAULT false NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE pestroutes.employees (
    id integer NOT NULL,
    area_id integer,
    is_active boolean,
    first_name text,
    last_name text,
    initials text,
    nickname text,
    type integer,
    phone text,
    email text,
    username text,
    experience integer,
    pic text,
    linked_employee_ids integer,
    employee_link text,
    license_number text,
    supervisor_employee_id integer,
    roaming_rep_employee_id integer,
    team_ids text,
    primary_team integer,
    date_updated timestamp with time zone,
    user_id uuid,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    pestroutes_json jsonb,
    deleted_at timestamp(6) with time zone
);

COMMENT ON COLUMN pestroutes.employees.id IS '{"pestroutes_column_name": "employeeID"}';

COMMENT ON COLUMN pestroutes.employees.is_active IS '{"pestroutes_column_name": "active"}';

COMMENT ON COLUMN pestroutes.employees.first_name IS '{"pestroutes_column_name": "fname"}';

COMMENT ON COLUMN pestroutes.employees.last_name IS '{"pestroutes_column_name": "lname"}';

COMMENT ON COLUMN pestroutes.employees.initials IS '{"pestroutes_column_name": "initials"}';

COMMENT ON COLUMN pestroutes.employees.nickname IS '{"pestroutes_column_name": "nickname"}';

COMMENT ON COLUMN pestroutes.employees.type IS '{"pestroutes_column_name": "type"}';

COMMENT ON COLUMN pestroutes.employees.phone IS '{"pestroutes_column_name": "phone"}';

COMMENT ON COLUMN pestroutes.employees.email IS '{"pestroutes_column_name": "email"}';

COMMENT ON COLUMN pestroutes.employees.username IS '{"pestroutes_column_name": "username"}';

COMMENT ON COLUMN pestroutes.employees.experience IS '{"pestroutes_column_name": "experience"}';

COMMENT ON COLUMN pestroutes.employees.pic IS '{"pestroutes_column_name": "pic"}';

COMMENT ON COLUMN pestroutes.employees.linked_employee_ids IS '{"pestroutes_column_name": "linkedEmployeeIDs"}';

COMMENT ON COLUMN pestroutes.employees.employee_link IS '{"pestroutes_column_name": "employeeLink"}';

COMMENT ON COLUMN pestroutes.employees.license_number IS '{"pestroutes_column_name": "licenseNumber"}';

COMMENT ON COLUMN pestroutes.employees.supervisor_employee_id IS '{"pestroutes_column_name": "supervisorID"}';

COMMENT ON COLUMN pestroutes.employees.roaming_rep_employee_id IS '{"pestroutes_column_name": "roamingRep"}';

COMMENT ON COLUMN pestroutes.employees.team_ids IS '{"pestroutes_column_name": "teamIDs"}';

COMMENT ON COLUMN pestroutes.employees.primary_team IS '{"pestroutes_column_name": "primaryTeam"}';

COMMENT ON COLUMN pestroutes.employees.date_updated IS '{"pestroutes_column_name": "dateUpdated"}';

CREATE TABLE pestroutes.etl_execution_steps (
    id integer NOT NULL,
    execution_id integer NOT NULL,
    start_at timestamp with time zone NOT NULL,
    end_at timestamp with time zone NOT NULL
);

ALTER TABLE pestroutes.etl_execution_steps ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME pestroutes.etl_execution_steps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE pestroutes.etl_executions (
    id integer NOT NULL,
    etl_range_start_at timestamp with time zone NOT NULL,
    etl_range_end_at timestamp with time zone NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL
);

ALTER TABLE pestroutes.etl_executions ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME pestroutes.etl_executions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE pestroutes.etl_writer_queue (
    id bigint NOT NULL,
    audit_table_name text NOT NULL,
    audit_table_id uuid NOT NULL,
    table_id uuid NOT NULL,
    operation text NOT NULL,
    row_new jsonb,
    row_old jsonb,
    row_diff jsonb,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL
);

ALTER TABLE pestroutes.etl_writer_queue ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME pestroutes.etl_writer_queue_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE pestroutes.etl_writer_queue_log (
    id bigint NOT NULL,
    queue_id bigint,
    audit_table_name text,
    audit_table_id uuid,
    table_id uuid,
    operation text,
    processing_status text NOT NULL,
    pestroutes_sync_status text,
    pestroutes_synced_at timestamp(6) with time zone,
    pestroutes_sync_error text,
    lambda_request_id character(36),
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL
);

ALTER TABLE pestroutes.etl_writer_queue_log ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME pestroutes.etl_writer_queue_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE product.plans (
    id integer NOT NULL,
    external_ref_id integer,
    name text,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE product.plans ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME product.plans_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE product.products (
    id integer NOT NULL,
    external_ref_id integer,
    name text NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone,
    pestroutes_json jsonb
);

ALTER TABLE product.products ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME product.products_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE public._primary_key_column_name (
    column_name information_schema.sql_identifier
);

CREATE TABLE public.awsdms_apply_exceptions (
    "TASK_NAME" character varying(128) NOT NULL,
    "TABLE_OWNER" character varying(128) NOT NULL,
    "TABLE_NAME" character varying(128) NOT NULL,
    "ERROR_TIME" timestamp without time zone NOT NULL,
    "STATEMENT" text NOT NULL,
    "ERROR" text NOT NULL
);

CREATE TABLE public.awsdms_ddl_audit (
    c_key bigint NOT NULL,
    c_time timestamp without time zone,
    c_user character varying(64),
    c_txn character varying(16),
    c_tag character varying(24),
    c_oid integer,
    c_name character varying(64),
    c_schema character varying(64),
    c_ddlqry text
);

CREATE SEQUENCE public.awsdms_ddl_audit_c_key_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.awsdms_ddl_audit_c_key_seq OWNED BY public.awsdms_ddl_audit.c_key;

CREATE TABLE public.awsdms_validation_failures_v1 (
    "TASK_NAME" character varying(128) NOT NULL,
    "TABLE_OWNER" character varying(128) NOT NULL,
    "TABLE_NAME" character varying(128) NOT NULL,
    "FAILURE_TIME" timestamp without time zone NOT NULL,
    "KEY_TYPE" character varying(128) NOT NULL,
    "KEY" character varying(7800) NOT NULL,
    "FAILURE_TYPE" character varying(128) NOT NULL,
    "DETAILS" character varying(7800) NOT NULL
);

CREATE SEQUENCE public.pins_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

CREATE TABLE public.staging_appointments (
    external_ref_id integer NOT NULL,
    date date,
    start_time time without time zone,
    end_time time without time zone,
    time_window text,
    call_ahead_in_mins integer,
    duration_in_mins integer,
    is_initial boolean,
    tech_time_in_at text,
    tech_time_out_at text,
    tech_check_in_at text,
    tech_check_out_at text,
    tech_check_in_lat double precision,
    tech_check_in_lng double precision,
    tech_check_out_lat double precision,
    tech_check_out_lng double precision,
    appt_notes text,
    office_notes text,
    do_interior integer,
    serviced_interior integer,
    wind_speed integer,
    wind_direction text,
    payment_method integer,
    amount_collected double precision,
    temperature double precision,
    completed_at text,
    cancelled_at text,
    pestroutes_office_id integer,
    pestroutes_customer_id integer,
    pestroutes_subscription_id integer,
    pestroutes_service_type_id integer,
    pestroutes_route_id integer,
    pestroutes_spot_id integer,
    pestroutes_invoice_id integer,
    pestroutes_status_id integer,
    pestroutes_created_by integer,
    pestroutes_assigned_tech integer,
    pestroutes_serviced_by integer,
    pestroutes_completed_by integer,
    pestroutes_cancelled_by integer,
    pestroutes_created_at text,
    pestroutes_completed_at text,
    pestroutes_cancelled_at text,
    pestroutes_updated_at text
);

CREATE TABLE public.staging_customers (
    external_ref_id integer NOT NULL,
    pestroutes_office_id integer NOT NULL,
    first_name text,
    last_name text,
    company_name text,
    is_active boolean DEFAULT true NOT NULL,
    email text,
    phone1 text,
    phone2 text,
    address text,
    city text,
    state text,
    zip text,
    lat double precision,
    lng double precision,
    source text,
    autopay_type text,
    paid_in_full boolean,
    balance double precision,
    balance_age integer,
    responsible_balance double precision,
    responsible_balance_age integer,
    pestroutes_master_account integer,
    preferred_billing_day_of_month integer,
    payment_hold_date text,
    most_recent_credit_card_last_four text,
    most_recent_credit_card_exp_date text,
    sms_reminders boolean DEFAULT false,
    phone_reminders boolean DEFAULT false,
    email_reminders boolean DEFAULT false,
    tax_rate double precision,
    billing_company_name text,
    billing_first_name text,
    billing_last_name text,
    billing_country text,
    billing_address text,
    billing_city text,
    billing_state text,
    billing_zip text,
    billing_phone text,
    billing_email text,
    pestroutes_created_by integer NOT NULL,
    pestroutes_source_id integer,
    pestroutes_preferred_tech_id integer,
    pestroutes_customer_link text,
    pestroutes_created_at text,
    pestroutes_cancelled_at text,
    pestroutes_updated_at text
);

CREATE TABLE public.staging_employees (
    id integer NOT NULL,
    area_id integer,
    is_active boolean,
    first_name text,
    last_name text,
    initials text,
    nickname text,
    type integer,
    phone text,
    email text,
    username text,
    experience integer,
    pic text,
    linked_employee_ids integer,
    employee_link text,
    license_number text,
    supervisor_employee_id integer,
    roaming_rep_employee_id integer,
    team_ids text,
    primary_team integer,
    date_updated timestamp with time zone
);

CREATE TABLE public.staging_invoices (
    external_ref_id integer NOT NULL,
    pestroutes_office_id integer,
    is_active boolean NOT NULL,
    subtotal double precision NOT NULL,
    tax_rate double precision NOT NULL,
    total double precision NOT NULL,
    balance double precision NOT NULL,
    service_charge double precision NOT NULL,
    pestroutes_customer_id integer NOT NULL,
    pestroutes_subscription_id integer NOT NULL,
    pestroutes_service_type_id integer NOT NULL,
    pestroutes_created_by integer,
    pestroutes_created_at text NOT NULL,
    pestroutes_invoiced_at text,
    pestroutes_updated_at text
);

CREATE TABLE public.staging_routes (
    external_ref_id integer NOT NULL,
    name text,
    date date,
    pestroutes_office_id integer NOT NULL,
    pestroutes_created_by integer,
    pestroutes_route_template_id integer,
    pestroutes_route_group_title text,
    pestroutes_assigned_tech_employee_id integer,
    pestroutes_created_at text,
    pestroutes_updated_at text
);

CREATE TABLE public.staging_service_types (
    id integer NOT NULL,
    pestroutes_office_id integer NOT NULL,
    name text
);

CREATE TABLE public.staging_subscriptions (
    external_ref_id integer NOT NULL,
    pestroutes_office_id integer,
    is_active boolean NOT NULL,
    initial_service_quote double precision,
    initial_service_discount double precision,
    initial_service_total double precision,
    initial_status_id integer,
    recurring_charge double precision,
    contract_value double precision,
    annual_recurring_value double precision,
    billing_frequency text,
    service_frequency text,
    days_til_follow_up_service integer,
    agreement_length_months integer,
    source text,
    cancellation_notes text,
    annual_recurring_services integer,
    renewal_frequency integer,
    max_monthly_charge double precision,
    initial_billing_date text,
    next_service_date text,
    last_service_date text,
    next_billing_date text,
    expiration_date text,
    custom_next_service_date text,
    appt_duration_in_mins integer,
    preferred_days_of_week text,
    preferred_start_time_of_day time without time zone,
    preferred_end_time_of_day time without time zone,
    addons jsonb,
    pestroutes_customer_id integer,
    pestroutes_created_by integer,
    pestroutes_sold_by integer,
    pestroutes_sold_by_2 integer,
    pestroutes_sold_by_3 integer,
    pestroutes_service_type_id integer,
    pestroutes_source_id integer,
    pestroutes_last_appointment_id integer,
    pestroutes_preferred_tech_id integer,
    pestroutes_initial_appt_id integer,
    pestroutes_recurring_ticket jsonb,
    pestroutes_subscription_link text,
    pestroutes_created_at text,
    pestroutes_cancelled_at text,
    pestroutes_updated_at text
);

CREATE TABLE public.test1 (
    id integer NOT NULL,
    name text,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE SEQUENCE public.test1_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.test1_id_seq OWNED BY public.test1.id;

CREATE TABLE public.user_knock_stats_historical (
    user_id integer NOT NULL,
    date date NOT NULL,
    total_knocks integer,
    decision_makers integer
);

CREATE TABLE street_smarts.knocks (
    id bigint NOT NULL,
    polygon_id integer NOT NULL,
    pin_id bigint,
    lat double precision,
    lon double precision,
    point public.geometry(Point,4326),
    rep_lat double precision,
    rep_lon double precision,
    rep_point public.geometry(Point,4326),
    address character varying(255),
    city character varying(255),
    state character varying(255),
    zip character(5),
    zip_4 character(4),
    user_id integer NOT NULL,
    team_id integer,
    outcome_id integer NOT NULL,
    season_id integer NOT NULL,
    is_cleared boolean NOT NULL,
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    pin_id_new bigint
);

CREATE TABLE street_smarts.outcomes (
    id integer NOT NULL,
    name character varying(255),
    is_final boolean,
    is_decision_maker boolean,
    icon_filename character varying(255),
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL
);

CREATE VIEW public.user_knock_stats AS
 SELECT k.user_id,
    (timezone('US/Pacific'::text, timezone('UTC'::text, k.created_at)))::date AS date,
    count(*) AS total_knocks,
    count(
        CASE
            WHEN o.is_decision_maker THEN true
            ELSE NULL::boolean
        END) AS decision_makers
   FROM (street_smarts.knocks k
     JOIN street_smarts.outcomes o ON ((k.outcome_id = o.id)))
  WHERE (k.created_at > timezone('UTC'::text, ((timezone('US/Pacific'::text, CURRENT_TIMESTAMP))::date)::timestamp with time zone))
  GROUP BY k.user_id, ((timezone('US/Pacific'::text, timezone('UTC'::text, k.created_at)))::date)
UNION ALL
 SELECT uksh.user_id,
    uksh.date,
    uksh.total_knocks,
    uksh.decision_makers
   FROM public.user_knock_stats_historical uksh;

CREATE TABLE sales.team_territories (
    team_id integer NOT NULL,
    territory_id integer NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE sales.teams (
    id integer NOT NULL,
    dealer_id integer NOT NULL,
    name character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE sales.teams ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME sales.teams_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE sales.territories (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE sales.territories ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME sales.territories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE sales.user_teams (
    user_id uuid NOT NULL,
    team_id integer NOT NULL,
    is_manager boolean NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE spt.area_extended (
    area_id integer NOT NULL,
    active_customers integer DEFAULT 0 NOT NULL,
    inactive_customers integer DEFAULT 0 NOT NULL,
    total_addresses integer DEFAULT 0,
    qualified_addresses integer DEFAULT 0 NOT NULL,
    rep_count integer DEFAULT 0 NOT NULL,
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone
);

CREATE TABLE spt.cells (
    area_id integer NOT NULL,
    resolution integer NOT NULL,
    total_addresses integer NOT NULL,
    qualified_addresses integer NOT NULL,
    active_customers integer NOT NULL,
    avg_weeks_since_last_knocked integer,
    boundary public.geometry(Polygon,4326) NOT NULL,
    avg_qualified_area_quartile integer
);

CREATE TABLE spt.cells_old (
    area_id integer NOT NULL,
    resolution integer NOT NULL,
    total_addresses integer DEFAULT 0 NOT NULL,
    qualified_addresses integer DEFAULT 0 NOT NULL,
    active_customers integer DEFAULT 0 NOT NULL,
    avg_weeks_since_last_knocked integer,
    boundary public.geometry(Polygon,4326) NOT NULL
);

CREATE TABLE spt.cluster (
    cluster_id character varying(10) NOT NULL,
    company_id integer DEFAULT 1 NOT NULL,
    latitude numeric(17,14),
    longitude numeric(17,14),
    geolocation public.geometry(Point,4326) NOT NULL,
    active boolean DEFAULT true NOT NULL,
    total_addresses integer DEFAULT 0 NOT NULL,
    active_customers integer DEFAULT 0 NOT NULL,
    inactive_customers integer DEFAULT 0 NOT NULL,
    qualified_addresses integer DEFAULT 0 NOT NULL,
    area_id integer,
    team_id integer,
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone
);

CREATE TABLE spt.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);

CREATE SEQUENCE spt.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE spt.migrations_id_seq OWNED BY spt.migrations.id;

CREATE TABLE spt.one_time_authentication (
    token character varying(32) NOT NULL,
    jwt character varying(65535) NOT NULL,
    created_at timestamp without time zone,
    expires_at timestamp without time zone
);

CREATE TABLE spt.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE SEQUENCE spt.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE spt.personal_access_tokens_id_seq OWNED BY spt.personal_access_tokens.id;

CREATE TABLE spt.polygon (
    polygon_id integer NOT NULL,
    boundary public.geometry(Polygon,4326) NOT NULL,
    team_id integer,
    active boolean DEFAULT true NOT NULL,
    rep_count integer DEFAULT 0 NOT NULL,
    center_latitude numeric(17,14),
    center_longitude numeric(17,14),
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at timestamp(6) without time zone,
    qualified_addresses integer DEFAULT 0
);

CREATE TABLE spt.polygon_cluster (
    polygon_id integer NOT NULL,
    cluster_id character varying(10) NOT NULL,
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL
);

CREATE SEQUENCE spt.polygon_polygon_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE spt.polygon_polygon_id_seq OWNED BY spt.polygon.polygon_id;

CREATE TABLE spt.polygon_rep (
    polygon_rep_id integer NOT NULL,
    polygon_id integer NOT NULL,
    user_id integer NOT NULL,
    active boolean DEFAULT true NOT NULL,
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at timestamp(6) without time zone
);

CREATE SEQUENCE spt.polygon_rep_polygon_rep_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE spt.polygon_rep_polygon_rep_id_seq OWNED BY spt.polygon_rep.polygon_rep_id;

CREATE TABLE spt.polygon_stats (
    polygon_id integer NOT NULL,
    qualified_addresses integer DEFAULT 0 NOT NULL,
    total_addresses integer DEFAULT 0 NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE spt.team_cluster (
    company_id integer NOT NULL,
    team_id integer NOT NULL,
    cluster_id character varying(10) NOT NULL,
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL
);

CREATE TABLE spt.team_extended (
    team_id integer NOT NULL,
    latitude numeric(17,14),
    longitude numeric(17,14),
    active_customers integer DEFAULT 0 NOT NULL,
    inactive_customers integer DEFAULT 0 NOT NULL,
    total_addresses integer DEFAULT 0,
    qualified_addresses integer DEFAULT 0 NOT NULL,
    rep_count integer DEFAULT 0 NOT NULL,
    boundary public.geometry(MultiPolygon,4326),
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL
);

CREATE TABLE spt_old.area_extended (
    company_id integer,
    area_id integer NOT NULL,
    active_customers bigint NOT NULL,
    inactive_customers bigint NOT NULL,
    total_addresses bigint,
    qualified_addresses bigint NOT NULL,
    rep_count bigint NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE spt_old.area_zip (
    area_id bigint NOT NULL,
    zip character varying(5) NOT NULL,
    company_id bigint NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE spt_old.cell (
    boundary bytea NOT NULL,
    area_id integer,
    resolution integer,
    qualified_pins integer,
    decile_on_qualified_pins integer
);

CREATE TABLE spt_old.cluster (
    cluster_id character varying(10) NOT NULL,
    company_id integer NOT NULL,
    latitude numeric(17,14),
    longitude numeric(17,14),
    geolocation bytea NOT NULL,
    active smallint NOT NULL,
    total_addresses integer NOT NULL,
    active_customers integer NOT NULL,
    inactive_customers integer NOT NULL,
    qualified_addresses integer NOT NULL,
    area_id integer,
    team_id integer,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE spt_old.migrations (
    company_id integer,
    id bigint NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);

CREATE TABLE spt_old.one_time_authentication (
    token character varying(32) NOT NULL,
    jwt character varying(65535) NOT NULL,
    created_at timestamp without time zone,
    expires_at timestamp without time zone
);

CREATE TABLE spt_old.personal_access_tokens (
    company_id integer,
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities character varying(65535),
    last_used_at timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE spt_old.polygon (
    company_id integer,
    polygon_id bigint NOT NULL,
    geojson text NOT NULL,
    team_id bigint,
    active smallint NOT NULL,
    rep_count bigint NOT NULL,
    center_latitude numeric(17,14),
    center_longitude numeric(17,14),
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    deleted_at timestamp without time zone
);

CREATE TABLE spt_old.polygon_cluster (
    company_id integer,
    polygon_id bigint NOT NULL,
    cluster_id character varying(10) NOT NULL
);

CREATE TABLE spt_old.polygon_rep (
    company_id integer,
    polygon_rep_id integer NOT NULL,
    polygon_id bigint NOT NULL,
    user_id bigint NOT NULL,
    active smallint NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    deleted_at timestamp without time zone
);

CREATE TABLE spt_old.team_cluster (
    company_id integer NOT NULL,
    team_id integer NOT NULL,
    cluster_id character varying(10) NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE spt_old.team_extended (
    company_id integer,
    team_id integer NOT NULL,
    latitude numeric(17,14),
    longitude numeric(17,14),
    active_customers bigint NOT NULL,
    inactive_customers bigint NOT NULL,
    total_addresses bigint,
    qualified_addresses bigint NOT NULL,
    rep_count bigint NOT NULL,
    geojson text,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    boundary bytea
);

CREATE TABLE spt_old.tmp_clusters (
    cluster_id character varying(10) NOT NULL,
    company_id integer NOT NULL,
    latitude numeric(17,14),
    longitude numeric(17,14),
    active smallint NOT NULL,
    total_addresses integer NOT NULL,
    active_customers integer NOT NULL,
    inactive_customers integer NOT NULL,
    qualified_addresses integer NOT NULL,
    area_id integer,
    team_id integer,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE spt_old.zip (
    zip character varying(5) NOT NULL,
    geojson text
);

CREATE TABLE street_smarts.cluster_pin (
    cluster_id character varying(10) NOT NULL,
    pin_id bigint NOT NULL,
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL
);

CREATE TABLE street_smarts.failed_jobs (
    id integer NOT NULL,
    uuid character varying(36) NOT NULL,
    connection text,
    queue text,
    payload character varying(45000),
    exception character varying(45000),
    failed_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE street_smarts.failed_jobs ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME street_smarts.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE street_smarts.infutor_scores (
    id bigint NOT NULL,
    is_qualified integer NOT NULL
);

CREATE TABLE street_smarts.knock_periods (
    team_id integer NOT NULL,
    cell_id bigint NOT NULL,
    season_id integer NOT NULL,
    period integer NOT NULL,
    date_start timestamp without time zone,
    date_end timestamp without time zone,
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL
);

CREATE SEQUENCE street_smarts.knocks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE street_smarts.knocks_id_seq OWNED BY street_smarts.knocks.id;

CREATE VIEW street_smarts.outcome AS
 SELECT outcomes.id AS outcome_id,
    outcomes.name,
    outcomes.is_final,
    outcomes.is_decision_maker,
    outcomes.icon_filename,
    outcomes.created_at,
    outcomes.updated_at
   FROM street_smarts.outcomes;

CREATE SEQUENCE street_smarts.outcomes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE street_smarts.outcomes_id_seq OWNED BY street_smarts.outcomes.id;

CREATE TABLE street_smarts.pins_old (
    id bigint NOT NULL,
    address character varying(255),
    city character varying(255),
    state character varying(255),
    zip character(5),
    zip_4 character(4),
    lat double precision NOT NULL,
    lon double precision NOT NULL,
    point public.geometry(Point,4326) NOT NULL,
    is_qualified boolean DEFAULT false NOT NULL,
    customer_id integer,
    first_name character varying(100),
    last_name character varying(100),
    customer_meta json,
    knock_count integer NOT NULL,
    current_note_id integer,
    current_outcome_id integer,
    last_knock_time timestamp without time zone,
    record_status character varying(24),
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at timestamp(6) without time zone
);

CREATE VIEW street_smarts.pin AS
 SELECT pins_old.id AS pin_id,
    pins_old.address,
    pins_old.city,
    pins_old.state,
    pins_old.zip,
    pins_old.zip_4,
    pins_old.lat,
    pins_old.lon,
    pins_old.point,
    pins_old.is_qualified,
    pins_old.customer_id,
    pins_old.first_name,
    pins_old.last_name,
    pins_old.knock_count,
    pins_old.current_note_id,
    pins_old.current_outcome_id,
    pins_old.last_knock_time,
    pins_old.record_status,
    pins_old.created_at,
    pins_old.updated_at,
    pins_old.deleted_at
   FROM street_smarts.pins_old;

CREATE TABLE street_smarts.pin_geocoding_overrides (
    pin_id bigint NOT NULL,
    lat double precision,
    lon double precision,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);

CREATE TABLE street_smarts.pin_history (
    id integer NOT NULL,
    pin_id integer NOT NULL,
    is_qualified boolean,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    is_dropped boolean
);

ALTER TABLE street_smarts.pin_history ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME street_smarts.pin_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE street_smarts.pin_issues (
    id integer NOT NULL,
    created_by integer NOT NULL,
    pin_id integer NOT NULL,
    address character varying(255) NOT NULL,
    city character varying(255) NOT NULL,
    state character varying(255) NOT NULL,
    zip character(5) NOT NULL,
    zip_4 character(4),
    lat double precision NOT NULL,
    lon double precision NOT NULL,
    issue character varying(255) NOT NULL,
    comment text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at timestamp(6) with time zone
);

ALTER TABLE street_smarts.pin_issues ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME street_smarts.pin_issues_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE street_smarts.pin_notes (
    id bigint NOT NULL,
    pin_id bigint,
    user_id integer,
    season_id integer,
    note character varying(5000),
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    pin_id_new bigint
);

CREATE SEQUENCE street_smarts.pin_notes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE street_smarts.pin_notes_id_seq OWNED BY street_smarts.pin_notes.id;

CREATE SEQUENCE street_smarts.pins_new_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

CREATE TABLE street_smarts.pins (
    id bigint DEFAULT nextval('street_smarts.pins_new_id_seq'::regclass) NOT NULL,
    address character varying(255),
    city character varying(255),
    state character varying(255),
    zip character(5),
    zip_4 character(4),
    lat double precision NOT NULL,
    lon double precision NOT NULL,
    point public.geometry(Point,4326) NOT NULL,
    is_qualified boolean,
    customer_meta jsonb,
    knock_count integer NOT NULL,
    current_note_id integer,
    current_outcome_id integer,
    last_knock_time timestamp without time zone,
    record_status character varying(24),
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at timestamp(6) without time zone,
    meta jsonb,
    is_dropped boolean DEFAULT false NOT NULL
);

CREATE TABLE street_smarts.s3_loads_log (
    lambda_request_id character(36) NOT NULL,
    target_table character varying(100),
    s3_key character varying(100),
    num_rows_loaded integer,
    completed boolean DEFAULT false NOT NULL,
    error character varying(5000),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone
);

CREATE TABLE street_smarts.tmp_new_pin_id_association (
    old_id bigint NOT NULL,
    new_id bigint
);

CREATE TABLE street_smarts.zips (
    zip character(5) NOT NULL,
    area_id integer,
    team_id integer,
    active_customers integer,
    inactive_customers integer,
    total_addresses integer,
    qualified_addresses integer,
    boundary public.geometry(Geometry,4326),
    created_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at timestamp(6) without time zone
);

CREATE TABLE street_smarts_old.cell_assignments (
    id integer NOT NULL,
    team_id smallint,
    cell_id bigint,
    user_id integer,
    season_id integer,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE street_smarts_old.cells (
    id bigint NOT NULL,
    area_id integer NOT NULL,
    team_id integer NOT NULL,
    "row" integer NOT NULL,
    col integer NOT NULL,
    polygon character varying(255) NOT NULL,
    average_score real NOT NULL,
    total_pins integer NOT NULL,
    knockable_pins integer,
    active smallint NOT NULL,
    knockable smallint NOT NULL,
    c_lat double precision NOT NULL,
    c_lon double precision NOT NULL,
    visible smallint NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE street_smarts_old.cells_01_04_load (
    id bigint NOT NULL,
    area_id integer NOT NULL,
    team_id integer NOT NULL,
    "row" integer NOT NULL,
    col integer NOT NULL,
    polygon character varying(255) NOT NULL,
    average_score real NOT NULL,
    total_pins integer NOT NULL,
    knockable_pins integer,
    active smallint NOT NULL,
    knockable smallint NOT NULL,
    c_lat double precision NOT NULL,
    c_lon double precision NOT NULL,
    visible smallint NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE street_smarts_old.cluster_pin (
    cluster_id character varying(10) NOT NULL,
    pin_id bigint NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE street_smarts_old.knock_periods (
    team_id smallint NOT NULL,
    cell_id bigint NOT NULL,
    season_id smallint NOT NULL,
    period smallint NOT NULL,
    date_start timestamp without time zone,
    date_end timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE street_smarts_old.knocks (
    id bigint NOT NULL,
    area_id smallint,
    team_id smallint,
    cell_id bigint,
    pin_id bigint NOT NULL,
    lat double precision,
    lon double precision,
    rep_lat double precision,
    rep_lon double precision,
    address character varying(255),
    city character varying(255),
    state character varying(255),
    zip integer,
    zip_4 integer,
    score smallint,
    income smallint,
    zip4_score smallint,
    user_id integer NOT NULL,
    outcome_id smallint NOT NULL,
    season_id smallint NOT NULL,
    is_cleared integer NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE street_smarts_old.outcomes (
    id integer NOT NULL,
    name character varying(255),
    is_final smallint,
    is_decision_maker smallint,
    icon_filename character varying(255),
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE street_smarts_old.pin_notes (
    id bigint NOT NULL,
    pin_id bigint,
    user_id integer,
    season_id smallint,
    note character varying(5000),
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE street_smarts_old.pins (
    id bigint NOT NULL,
    area_id smallint NOT NULL,
    team_id smallint NOT NULL,
    cell_id bigint NOT NULL,
    customer_id integer,
    first_name character varying(100),
    last_name character varying(100),
    lat double precision NOT NULL,
    lon double precision NOT NULL,
    address character varying(255),
    city character varying(255),
    state character varying(255),
    zip character varying(5),
    zip_4 character varying(4),
    score smallint,
    income smallint,
    zip4_score smallint,
    knock_count smallint NOT NULL,
    current_note_id integer,
    current_outcome_id smallint,
    last_knock_time timestamp without time zone,
    record_status character varying(24),
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

CREATE TABLE street_smarts_old.pins_temp (
    id bigint NOT NULL,
    area_id smallint NOT NULL,
    cell_id bigint NOT NULL,
    customer_id integer,
    address character varying(255),
    city character varying(255),
    state character varying(255),
    zip character varying(5),
    zip_4 integer,
    score smallint,
    income smallint,
    record_status character varying(24)
);

CREATE TABLE street_smarts_old.tmp_deleted_pins (
    id bigint NOT NULL,
    area_id smallint NOT NULL,
    team_id smallint NOT NULL,
    cell_id bigint NOT NULL,
    customer_id integer,
    first_name character varying(100),
    last_name character varying(100),
    lat double precision NOT NULL,
    lon double precision NOT NULL,
    address character varying(255),
    city character varying(255),
    state character varying(255),
    zip character varying(5),
    zip_4 character varying(4),
    score smallint,
    income smallint,
    zip4_score smallint,
    knock_count smallint NOT NULL,
    current_note_id integer,
    current_outcome_id smallint,
    last_knock_time timestamp without time zone,
    record_status character varying(24),
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);

ALTER TABLE ONLY public.awsdms_ddl_audit ALTER COLUMN c_key SET DEFAULT nextval('public.awsdms_ddl_audit_c_key_seq'::regclass);

ALTER TABLE ONLY public.test1 ALTER COLUMN id SET DEFAULT nextval('public.test1_id_seq'::regclass);

ALTER TABLE ONLY spt.migrations ALTER COLUMN id SET DEFAULT nextval('spt.migrations_id_seq'::regclass);

ALTER TABLE ONLY spt.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('spt.personal_access_tokens_id_seq'::regclass);

ALTER TABLE ONLY spt.polygon ALTER COLUMN polygon_id SET DEFAULT nextval('spt.polygon_polygon_id_seq'::regclass);

ALTER TABLE ONLY spt.polygon_rep ALTER COLUMN polygon_rep_id SET DEFAULT nextval('spt.polygon_rep_polygon_rep_id_seq'::regclass);

ALTER TABLE ONLY street_smarts.knocks ALTER COLUMN id SET DEFAULT nextval('street_smarts.knocks_id_seq'::regclass);

ALTER TABLE ONLY street_smarts.outcomes ALTER COLUMN id SET DEFAULT nextval('street_smarts.outcomes_id_seq'::regclass);

ALTER TABLE ONLY street_smarts.pin_notes ALTER COLUMN id SET DEFAULT nextval('street_smarts.pin_notes_id_seq'::regclass);

ALTER TABLE ONLY alterra_production.ecosure_contacts
    ADD CONSTRAINT ecosure_contacts_pkey PRIMARY KEY (id);

ALTER TABLE ONLY alterra_production.ecosure_housing_tbl_team
    ADD CONSTRAINT ecosure_housing_tbl_team_pkey PRIMARY KEY (id);

ALTER TABLE ONLY alterra_production.ecosure_housing_team_slots
    ADD CONSTRAINT ecosure_housing_team_slots_pkey PRIMARY KEY (id);

ALTER TABLE ONLY alterra_production.ecosure_office_branches
    ADD CONSTRAINT ecosure_office_branches_pkey PRIMARY KEY (id);

ALTER TABLE ONLY alterra_production.ecosure_offices
    ADD CONSTRAINT ecosure_offices_pkey PRIMARY KEY (id);

ALTER TABLE ONLY alterra_production.ecosure_recruiting_seasons
    ADD CONSTRAINT ecosure_recruiting_seasons_pkey PRIMARY KEY (id);

ALTER TABLE ONLY alterra_production.ecosure_recruits
    ADD CONSTRAINT ecosure_recruits_pkey PRIMARY KEY (id);

ALTER TABLE ONLY alterra_production.ecosure_scouts
    ADD CONSTRAINT ecosure_scouts_pkey PRIMARY KEY (id);

ALTER TABLE ONLY alterra_production.ecosure_seasons
    ADD CONSTRAINT ecosure_seasons_pkey PRIMARY KEY (id);

ALTER TABLE ONLY alterra_production.ecosure_users
    ADD CONSTRAINT ecosure_users_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__decline_reasons
    ADD CONSTRAINT billing__decline_reasons_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__default_autopay_payment_methods
    ADD CONSTRAINT billing__default_autopay_payment_methods_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__distinct_payment_methods
    ADD CONSTRAINT billing__distinct_payment_methods_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__failed_jobs
    ADD CONSTRAINT billing__failed_jobs_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__failed_refund_payments
    ADD CONSTRAINT billing__failed_refund_payments_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__invoices
    ADD CONSTRAINT billing__invoices_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__ledger
    ADD CONSTRAINT billing__ledger_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__new_payments_with_last_four
    ADD CONSTRAINT billing__new_payments_with_last_four_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__payment_methods
    ADD CONSTRAINT billing__payment_methods_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__payment_update_last4_log
    ADD CONSTRAINT billing__payment_update_last4_log_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__payment_update_pestroutes_created_by_crm_log
    ADD CONSTRAINT billing__payment_update_pestroutes_created_by_crm_log_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__payments
    ADD CONSTRAINT billing__payments_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__scheduled_payment_statuses
    ADD CONSTRAINT billing__scheduled_payment_statuses_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__scheduled_payment_triggers
    ADD CONSTRAINT billing__scheduled_payment_triggers_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__scheduled_payments
    ADD CONSTRAINT billing__scheduled_payments_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__subscription_autopay_payment_methods
    ADD CONSTRAINT billing__subscription_autopay_payment_methods_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__suspend_reasons
    ADD CONSTRAINT billing__suspend_reasons_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.billing__test_payments
    ADD CONSTRAINT billing__test_payments_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.customer__accounts
    ADD CONSTRAINT customer__accounts_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.customer__addresses
    ADD CONSTRAINT customer__addresses_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.customer__contacts
    ADD CONSTRAINT customer__contacts_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.customer__contracts
    ADD CONSTRAINT customer__contracts_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.customer__documents
    ADD CONSTRAINT customer__documents_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.customer__forms
    ADD CONSTRAINT customer__forms_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.customer__notes
    ADD CONSTRAINT customer__notes_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.customer__subscriptions
    ADD CONSTRAINT customer__subscriptions_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.dbe_test__test_tbl
    ADD CONSTRAINT dbe_test__test_tbl_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__appointments
    ADD CONSTRAINT field_operations__appointments_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__aro_users
    ADD CONSTRAINT field_operations__aro_users_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__customer_property_details
    ADD CONSTRAINT field_operations__customer_property_details_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__monthly_financial_reports
    ADD CONSTRAINT field_operations__monthly_financial_reports_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__notification_recipient_type
    ADD CONSTRAINT field_operations__notification_recipient_type_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__notification_recipients
    ADD CONSTRAINT field_operations__notification_recipients_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__notification_types
    ADD CONSTRAINT field_operations__notification_types_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__route_details
    ADD CONSTRAINT field_operations__route_details_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__route_geometries
    ADD CONSTRAINT field_operations__route_geometries_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__route_templates
    ADD CONSTRAINT field_operations__route_templates_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__routes
    ADD CONSTRAINT field_operations__routes_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__scheduled_route_details
    ADD CONSTRAINT field_operations__scheduled_route_details_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__scheduling_states
    ADD CONSTRAINT field_operations__scheduling_states_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__service_types
    ADD CONSTRAINT field_operations__service_types_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__serviced_route_details
    ADD CONSTRAINT field_operations__serviced_route_details_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.field_operations__treatment_states
    ADD CONSTRAINT field_operations__treatment_states_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.notifications__cache
    ADD CONSTRAINT notifications__cache_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.notifications__headshot_paths
    ADD CONSTRAINT notifications__headshot_paths_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.notifications__incoming_mms_messages
    ADD CONSTRAINT notifications__incoming_mms_messages_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.notifications__logs_email
    ADD CONSTRAINT notifications__logs_email_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.notifications__logs
    ADD CONSTRAINT notifications__logs_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.notifications__notifications_sent_batches
    ADD CONSTRAINT notifications__notifications_sent_batches_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.notifications__sms_messages_media
    ADD CONSTRAINT notifications__sms_messages_media_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.notifications__sms_messages
    ADD CONSTRAINT notifications__sms_messages_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.pestroutes__etl_writer_queue_log
    ADD CONSTRAINT pestroutes__etl_writer_queue_log_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.pestroutes__etl_writer_queue
    ADD CONSTRAINT pestroutes__etl_writer_queue_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.pestroutes__queue_log
    ADD CONSTRAINT pestroutes__queue_log_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.pestroutes__queue
    ADD CONSTRAINT pestroutes__queue_pkey PRIMARY KEY (id);

ALTER TABLE ONLY audit.spt__polygon_stats
    ADD CONSTRAINT spt__polygon_stats_pkey PRIMARY KEY (id);

ALTER TABLE ONLY auth.actions
    ADD CONSTRAINT actions_name_key UNIQUE (name);

ALTER TABLE ONLY auth.actions
    ADD CONSTRAINT actions_pkey PRIMARY KEY (id);

ALTER TABLE ONLY auth.api_account_roles
    ADD CONSTRAINT api_account_roles_pkey PRIMARY KEY (id);

ALTER TABLE ONLY auth.fields
    ADD CONSTRAINT fields_name_key UNIQUE (name);

ALTER TABLE ONLY auth.fields
    ADD CONSTRAINT fields_pkey PRIMARY KEY (id);

ALTER TABLE ONLY auth.idp_roles
    ADD CONSTRAINT idp_roles_pkey PRIMARY KEY (id);

ALTER TABLE ONLY auth.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);

ALTER TABLE ONLY auth.resources
    ADD CONSTRAINT resources_name_key UNIQUE (name);

ALTER TABLE ONLY auth.resources
    ADD CONSTRAINT resources_pkey PRIMARY KEY (id);

ALTER TABLE ONLY auth.role_permissions
    ADD CONSTRAINT role_permissions_pkey PRIMARY KEY (id);

ALTER TABLE ONLY auth.roles
    ADD CONSTRAINT roles_name_key UNIQUE (name);

ALTER TABLE ONLY auth.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);

ALTER TABLE ONLY auth.services
    ADD CONSTRAINT services_name_key UNIQUE (name);

ALTER TABLE ONLY auth.services
    ADD CONSTRAINT services_pkey PRIMARY KEY (id);

ALTER TABLE ONLY auth.user_roles
    ADD CONSTRAINT user_roles_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.account_updater_attempts_methods
    ADD CONSTRAINT account_updater_attempts_methods_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.account_updater_attempts
    ADD CONSTRAINT account_updater_attempts_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.account_updater_attempts_methods
    ADD CONSTRAINT account_updater_attempts_unique_attempt_sequence_number UNIQUE (attempt_id, sequence_number);

ALTER TABLE ONLY billing.decline_reasons
    ADD CONSTRAINT decline_reasons_name_unique UNIQUE (name);

ALTER TABLE ONLY billing.decline_reasons
    ADD CONSTRAINT decline_reasons_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.default_autopay_payment_methods
    ADD CONSTRAINT default_autopay_payment_methods_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_key UNIQUE (uuid);

ALTER TABLE ONLY billing.failed_refund_payments
    ADD CONSTRAINT failed_refund_payments_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.invoice_items
    ADD CONSTRAINT invoice_items_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY billing.invoice_items
    ADD CONSTRAINT invoice_items_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.invoices
    ADD CONSTRAINT invoices_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY billing.invoices
    ADD CONSTRAINT invoices_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.ledger
    ADD CONSTRAINT ledger_account_id_unique UNIQUE (account_id);

ALTER TABLE ONLY billing.ledger
    ADD CONSTRAINT ledger_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.ledger_transactions
    ADD CONSTRAINT ledger_transactions_invoice_id_key UNIQUE (invoice_id);

ALTER TABLE ONLY billing.ledger_transactions
    ADD CONSTRAINT ledger_transactions_payment_id_key UNIQUE (payment_id);

ALTER TABLE ONLY billing.ledger_transactions
    ADD CONSTRAINT ledger_transactions_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.payment_gateways
    ADD CONSTRAINT payment_gateways_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.payment_invoice_allocations
    ADD CONSTRAINT payment_invoice_allocations_pk UNIQUE (payment_id, invoice_id);

ALTER TABLE ONLY billing.payment_invoice_allocations
    ADD CONSTRAINT payment_invoice_allocations_pk2 UNIQUE (pestroutes_payment_id, pestroutes_invoice_id);

ALTER TABLE ONLY billing.payment_invoice_allocations
    ADD CONSTRAINT payment_invoice_allocations_pk3 PRIMARY KEY (payment_id, invoice_id);

ALTER TABLE ONLY billing.payment_methods
    ADD CONSTRAINT payment_methods_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY billing.payment_methods
    ADD CONSTRAINT payment_methods_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.payment_statuses
    ADD CONSTRAINT payment_statuses_pk UNIQUE (name);

ALTER TABLE ONLY billing.payment_statuses
    ADD CONSTRAINT payment_statuses_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.payment_types
    ADD CONSTRAINT payment_types_pk UNIQUE (name);

ALTER TABLE ONLY billing.payment_types
    ADD CONSTRAINT payment_types_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.payment_types
    ADD CONSTRAINT payment_types_sort_order_key UNIQUE (sort_order);

ALTER TABLE ONLY billing.payment_update_pestroutes_created_by_crm_log
    ADD CONSTRAINT payment_update_pestroutes_created_by_crm_log_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.payments
    ADD CONSTRAINT payments_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY billing.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.scheduled_payment_statuses
    ADD CONSTRAINT scheduled_payment_statuses_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.scheduled_payment_triggers
    ADD CONSTRAINT scheduled_payment_triggers_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.scheduled_payments
    ADD CONSTRAINT scheduled_payments_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.subscription_autopay_payment_methods
    ADD CONSTRAINT subscription_autopay_payment_methods_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.suspend_reasons
    ADD CONSTRAINT suspend_reasons_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.transaction_types
    ADD CONSTRAINT transaction_types_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.transactions
    ADD CONSTRAINT transactions_pkey PRIMARY KEY (id);

ALTER TABLE ONLY billing.suspend_reasons
    ADD CONSTRAINT uk_suspend_reasons_name UNIQUE (name);

ALTER TABLE ONLY crm.generic_flags
    ADD CONSTRAINT generic_flags_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY crm.generic_flags
    ADD CONSTRAINT generic_flags_pk PRIMARY KEY (id);

ALTER TABLE ONLY customer.accounts
    ADD CONSTRAINT accounts_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY customer.accounts
    ADD CONSTRAINT accounts_pkey PRIMARY KEY (id);

ALTER TABLE ONLY customer.addresses
    ADD CONSTRAINT addresses_pkey PRIMARY KEY (id);

ALTER TABLE ONLY customer.cancellation_reasons
    ADD CONSTRAINT cancellation_reasons_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY customer.cancellation_reasons
    ADD CONSTRAINT cancellation_reasons_pkey PRIMARY KEY (id);

ALTER TABLE ONLY customer.contacts
    ADD CONSTRAINT contacts_pkey PRIMARY KEY (id);

ALTER TABLE ONLY customer.contracts
    ADD CONSTRAINT contracts_pkey PRIMARY KEY (id);

ALTER TABLE ONLY customer.documents
    ADD CONSTRAINT documents_pkey PRIMARY KEY (id);

ALTER TABLE ONLY customer.forms
    ADD CONSTRAINT forms_pkey PRIMARY KEY (id);

ALTER TABLE ONLY customer.note_types
    ADD CONSTRAINT note_types_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY customer.note_types
    ADD CONSTRAINT note_types_pkey PRIMARY KEY (id);

ALTER TABLE ONLY customer.notes
    ADD CONSTRAINT notes_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY customer.notes
    ADD CONSTRAINT notes_pkey PRIMARY KEY (id);

ALTER TABLE ONLY customer.subscriptions
    ADD CONSTRAINT subscriptions_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY customer.subscriptions
    ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);

ALTER TABLE ONLY dbe_test.test_tbl
    ADD CONSTRAINT actions_name_key UNIQUE (name);

ALTER TABLE ONLY dbe_test.test_tbl
    ADD CONSTRAINT actions_pkey PRIMARY KEY (id);

ALTER TABLE ONLY dbe_test.test_key_tbl
    ADD CONSTRAINT test_key_tbl_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.appointment_statuses
    ADD CONSTRAINT appointment_statuses_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY field_operations.appointment_statuses
    ADD CONSTRAINT appointment_statuses_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.appointment_types
    ADD CONSTRAINT appointment_types_pk UNIQUE (name);

ALTER TABLE ONLY field_operations.appointment_types
    ADD CONSTRAINT appointment_types_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.appointments
    ADD CONSTRAINT appointments_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY field_operations.appointments
    ADD CONSTRAINT appointments_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.areas
    ADD CONSTRAINT areas_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY field_operations.areas
    ADD CONSTRAINT areas_name_key UNIQUE (name);

ALTER TABLE ONLY field_operations.areas
    ADD CONSTRAINT areas_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.aro_failed_jobs
    ADD CONSTRAINT aro_failed_jobs_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.aro_failed_jobs
    ADD CONSTRAINT aro_failed_jobs_uuid_key UNIQUE (uuid);

ALTER TABLE ONLY field_operations.aro_users
    ADD CONSTRAINT aro_users_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.customer_property_details
    ADD CONSTRAINT customer_property_details_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.failure_notification_recipients
    ADD CONSTRAINT failure_notification_recipients_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.markets
    ADD CONSTRAINT markets_name_key UNIQUE (name);

ALTER TABLE ONLY field_operations.markets
    ADD CONSTRAINT markets_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.monthly_financial_reports
    ADD CONSTRAINT monthly_financial_reports_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.notification_recipient_type
    ADD CONSTRAINT notification_recipient_type_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.notification_recipients
    ADD CONSTRAINT notification_recipients_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.notification_types
    ADD CONSTRAINT notification_types_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.notification_types
    ADD CONSTRAINT notification_types_type_key UNIQUE (type);

ALTER TABLE ONLY field_operations.office_days_participants
    ADD CONSTRAINT office_days_participants_pkey PRIMARY KEY (schedule_id, employee_id);

ALTER TABLE ONLY field_operations.office_days_schedule_overrides
    ADD CONSTRAINT office_days_schedule_overrides_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.office_days_schedule
    ADD CONSTRAINT office_days_schedule_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.optimization_states
    ADD CONSTRAINT optimization_states_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.regions
    ADD CONSTRAINT regions_name_key UNIQUE (name);

ALTER TABLE ONLY field_operations.regions
    ADD CONSTRAINT regions_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.route_actual_stats
    ADD CONSTRAINT route_actual_stats_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.route_details
    ADD CONSTRAINT route_details_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.route_geometries
    ADD CONSTRAINT route_geometries_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.route_groups
    ADD CONSTRAINT route_groups_name_key UNIQUE (name);

ALTER TABLE ONLY field_operations.route_groups
    ADD CONSTRAINT route_groups_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.route_stats
    ADD CONSTRAINT route_stats_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.route_templates
    ADD CONSTRAINT route_templates_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY field_operations.route_templates
    ADD CONSTRAINT route_templates_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.routes
    ADD CONSTRAINT routes_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY field_operations.routes
    ADD CONSTRAINT routes_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.scheduled_route_details
    ADD CONSTRAINT scheduled_route_details_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.scheduling_states
    ADD CONSTRAINT scheduling_states_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.service_actual_stats
    ADD CONSTRAINT service_actual_stats_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.service_types
    ADD CONSTRAINT service_types_new_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY field_operations.service_types
    ADD CONSTRAINT service_types_new_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.serviced_route_details
    ADD CONSTRAINT serviced_route_details_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.treatment_states
    ADD CONSTRAINT treatment_states_pkey PRIMARY KEY (id);

ALTER TABLE ONLY field_operations.user_areas
    ADD CONSTRAINT user_areas_pkey PRIMARY KEY (user_id, area_id);

ALTER TABLE ONLY licensing.counties
    ADD CONSTRAINT counties_name_state_id_key UNIQUE (name, state_id);

ALTER TABLE ONLY licensing.counties
    ADD CONSTRAINT counties_pkey PRIMARY KEY (id);

ALTER TABLE ONLY licensing.municipalities
    ADD CONSTRAINT municipalities_name_state_id_key UNIQUE (name, state_id);

ALTER TABLE ONLY licensing.municipalities
    ADD CONSTRAINT municipalities_pkey PRIMARY KEY (id);

ALTER TABLE ONLY licensing.states
    ADD CONSTRAINT states_pkey PRIMARY KEY (id);

ALTER TABLE ONLY licensing.user_county_licenses
    ADD CONSTRAINT user_county_licenses_pkey PRIMARY KEY (user_id, county_id);

ALTER TABLE ONLY licensing.user_denials_log
    ADD CONSTRAINT user_denials_log_pkey PRIMARY KEY (id);

ALTER TABLE ONLY licensing.user_municipality_licenses
    ADD CONSTRAINT user_municipality_licenses_pkey PRIMARY KEY (user_id, municipality_id);

ALTER TABLE ONLY licensing.user_state_licenses
    ADD CONSTRAINT user_state_licenses_pkey PRIMARY KEY (user_id, state_id);

ALTER TABLE ONLY notifications.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (id);

ALTER TABLE ONLY notifications.headshot_paths
    ADD CONSTRAINT headshot_paths_pkey PRIMARY KEY (id);

ALTER TABLE ONLY notifications.incoming_mms_messages
    ADD CONSTRAINT incoming_mms_messages_pkey PRIMARY KEY (id);

ALTER TABLE ONLY notifications.logs_email
    ADD CONSTRAINT logs_email_pkey PRIMARY KEY (id);

ALTER TABLE ONLY notifications.logs
    ADD CONSTRAINT logs_pkey PRIMARY KEY (id);

ALTER TABLE ONLY notifications.notifications_sent_batches
    ADD CONSTRAINT notifications_sent_batches_pkey PRIMARY KEY (id);

ALTER TABLE ONLY notifications.notifications_sent
    ADD CONSTRAINT pk_notifications_sent_id PRIMARY KEY (id);

ALTER TABLE ONLY notifications.sms_messages
    ADD CONSTRAINT pk_sms_messages_id PRIMARY KEY (id);

ALTER TABLE ONLY notifications.sms_messages_media
    ADD CONSTRAINT pk_sms_messages_media_id PRIMARY KEY (id);

ALTER TABLE ONLY organization.api_accounts
    ADD CONSTRAINT api_accounts_pkey PRIMARY KEY (id);

ALTER TABLE ONLY organization.dealers
    ADD CONSTRAINT dealers_name_key UNIQUE (name);

ALTER TABLE ONLY organization.dealers
    ADD CONSTRAINT dealers_pkey PRIMARY KEY (id);

ALTER TABLE ONLY organization.user_dealers
    ADD CONSTRAINT user_dealers_pkey PRIMARY KEY (user_id, dealer_id);

ALTER TABLE ONLY organization.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);

ALTER TABLE ONLY pestroutes.employees
    ADD CONSTRAINT employees_pkey PRIMARY KEY (id);

ALTER TABLE ONLY pestroutes.etl_execution_steps
    ADD CONSTRAINT etl_execution_steps_pkey PRIMARY KEY (id);

ALTER TABLE ONLY pestroutes.etl_executions
    ADD CONSTRAINT etl_executions_pkey PRIMARY KEY (id);

ALTER TABLE ONLY pestroutes.etl_writer_queue_log
    ADD CONSTRAINT etl_writer_queue_log_pkey PRIMARY KEY (id);

ALTER TABLE ONLY pestroutes.etl_writer_queue
    ADD CONSTRAINT etl_writer_queue_pkey PRIMARY KEY (id);

ALTER TABLE ONLY product.plans
    ADD CONSTRAINT plans_pkey PRIMARY KEY (id);

ALTER TABLE ONLY product.products
    ADD CONSTRAINT products_external_ref_id_key UNIQUE (external_ref_id);

ALTER TABLE ONLY product.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.awsdms_ddl_audit
    ADD CONSTRAINT awsdms_ddl_audit_pkey PRIMARY KEY (c_key);

ALTER TABLE ONLY public.staging_appointments
    ADD CONSTRAINT staging_appointments_pkey PRIMARY KEY (external_ref_id);

ALTER TABLE ONLY public.staging_customers
    ADD CONSTRAINT staging_customers_pkey PRIMARY KEY (external_ref_id);

ALTER TABLE ONLY public.staging_employees
    ADD CONSTRAINT staging_employees_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.staging_invoices
    ADD CONSTRAINT staging_invoices_pkey PRIMARY KEY (external_ref_id);

ALTER TABLE ONLY public.staging_routes
    ADD CONSTRAINT staging_routes_pkey PRIMARY KEY (external_ref_id);

ALTER TABLE ONLY public.staging_service_types
    ADD CONSTRAINT staging_service_types_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.staging_subscriptions
    ADD CONSTRAINT staging_subscriptions_pkey PRIMARY KEY (external_ref_id);

ALTER TABLE ONLY public.test1
    ADD CONSTRAINT test1_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.user_knock_stats_historical
    ADD CONSTRAINT user_knock_stats_historical_pk PRIMARY KEY (user_id, date);

ALTER TABLE ONLY sales.team_territories
    ADD CONSTRAINT team_territories_pkey PRIMARY KEY (team_id, territory_id);

ALTER TABLE ONLY sales.teams
    ADD CONSTRAINT teams_pk UNIQUE (dealer_id, name);

ALTER TABLE ONLY sales.teams
    ADD CONSTRAINT teams_pkey PRIMARY KEY (id);

ALTER TABLE ONLY sales.territories
    ADD CONSTRAINT territories_name_key UNIQUE (name);

ALTER TABLE ONLY sales.territories
    ADD CONSTRAINT territories_pkey PRIMARY KEY (id);

ALTER TABLE ONLY sales.user_teams
    ADD CONSTRAINT user_teams_pkey PRIMARY KEY (user_id, team_id);

ALTER TABLE ONLY spt.area_extended
    ADD CONSTRAINT area_extended_pkey PRIMARY KEY (area_id);

ALTER TABLE ONLY spt.polygon_cluster
    ADD CONSTRAINT cluster_id UNIQUE (cluster_id, polygon_id);

ALTER TABLE ONLY spt.cluster
    ADD CONSTRAINT cluster_pkey PRIMARY KEY (cluster_id, company_id);

ALTER TABLE ONLY spt.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);

ALTER TABLE ONLY spt.one_time_authentication
    ADD CONSTRAINT one_time_authentication_pkey PRIMARY KEY (token);

ALTER TABLE ONLY spt.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);

ALTER TABLE ONLY spt.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);

ALTER TABLE ONLY spt.polygon_cluster
    ADD CONSTRAINT polygon_cluster_pkey PRIMARY KEY (polygon_id, cluster_id);

ALTER TABLE ONLY spt.polygon
    ADD CONSTRAINT polygon_pkey PRIMARY KEY (polygon_id);

ALTER TABLE ONLY spt.polygon_rep
    ADD CONSTRAINT polygon_rep_pkey PRIMARY KEY (polygon_rep_id);

ALTER TABLE ONLY spt.polygon_rep
    ADD CONSTRAINT polygon_rep_polygon_rep UNIQUE (polygon_id, user_id);

ALTER TABLE ONLY spt.polygon_stats
    ADD CONSTRAINT polygon_stats_pkey PRIMARY KEY (polygon_id);

ALTER TABLE ONLY spt.team_cluster
    ADD CONSTRAINT team_cluster_pkey PRIMARY KEY (company_id, team_id, cluster_id);

ALTER TABLE ONLY spt.team_extended
    ADD CONSTRAINT team_extended_pkey PRIMARY KEY (team_id);

ALTER TABLE ONLY spt_old.area_extended
    ADD CONSTRAINT area_extended_pkey PRIMARY KEY (area_id);

ALTER TABLE ONLY spt_old.area_zip
    ADD CONSTRAINT area_zip_pkey PRIMARY KEY (area_id, zip, company_id);

ALTER TABLE ONLY spt_old.cluster
    ADD CONSTRAINT cluster_pkey PRIMARY KEY (cluster_id, company_id);

ALTER TABLE ONLY spt_old.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);

ALTER TABLE ONLY spt_old.one_time_authentication
    ADD CONSTRAINT one_time_authentication_pkey PRIMARY KEY (token);

ALTER TABLE ONLY spt_old.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);

ALTER TABLE ONLY spt_old.polygon_cluster
    ADD CONSTRAINT polygon_cluster_pkey PRIMARY KEY (polygon_id, cluster_id);

ALTER TABLE ONLY spt_old.polygon
    ADD CONSTRAINT polygon_pkey PRIMARY KEY (polygon_id);

ALTER TABLE ONLY spt_old.polygon_rep
    ADD CONSTRAINT polygon_rep_pkey PRIMARY KEY (polygon_rep_id);

ALTER TABLE ONLY spt_old.team_cluster
    ADD CONSTRAINT team_cluster_pkey PRIMARY KEY (company_id, team_id, cluster_id);

ALTER TABLE ONLY spt_old.team_extended
    ADD CONSTRAINT team_extended_pkey PRIMARY KEY (team_id);

ALTER TABLE ONLY spt_old.tmp_clusters
    ADD CONSTRAINT tmp_clusters_pkey PRIMARY KEY (cluster_id, company_id);

ALTER TABLE ONLY spt_old.zip
    ADD CONSTRAINT zip_pkey PRIMARY KEY (zip);

ALTER TABLE ONLY street_smarts.cluster_pin
    ADD CONSTRAINT cluster_pin_pkey PRIMARY KEY (cluster_id, pin_id);

ALTER TABLE ONLY street_smarts.pins
    ADD CONSTRAINT constraint_name UNIQUE (address, zip, zip_4);

ALTER TABLE ONLY street_smarts.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_key UNIQUE (uuid);

ALTER TABLE ONLY street_smarts.infutor_scores
    ADD CONSTRAINT infutor_scores_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts.knock_periods
    ADD CONSTRAINT knock_periods_pkey PRIMARY KEY (team_id, cell_id, season_id, period);

ALTER TABLE ONLY street_smarts.knocks
    ADD CONSTRAINT knocks_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts.outcomes
    ADD CONSTRAINT outcomes_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts.pin_geocoding_overrides
    ADD CONSTRAINT pin_geocoding_overrides_pkey PRIMARY KEY (pin_id);

ALTER TABLE ONLY street_smarts.pin_history
    ADD CONSTRAINT pin_history_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts.pin_issues
    ADD CONSTRAINT pin_issues_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts.pin_notes
    ADD CONSTRAINT pin_notes_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts.pins
    ADD CONSTRAINT pins_new_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts.pins_old
    ADD CONSTRAINT pins_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts.s3_loads_log
    ADD CONSTRAINT s3_loads_log_pkey PRIMARY KEY (lambda_request_id);

ALTER TABLE ONLY street_smarts.tmp_new_pin_id_association
    ADD CONSTRAINT tmp_new_pin_id_association_pkey PRIMARY KEY (old_id);

ALTER TABLE ONLY street_smarts.zips
    ADD CONSTRAINT zips_new_pkey PRIMARY KEY (zip);

ALTER TABLE ONLY street_smarts_old.cell_assignments
    ADD CONSTRAINT cell_assignments_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts_old.cells_01_04_load
    ADD CONSTRAINT cells_01_04_load_pkey PRIMARY KEY (id, area_id, team_id);

ALTER TABLE ONLY street_smarts_old.cells
    ADD CONSTRAINT cells_pkey PRIMARY KEY (id, area_id, team_id);

ALTER TABLE ONLY street_smarts_old.cluster_pin
    ADD CONSTRAINT cluster_pin_pkey PRIMARY KEY (cluster_id, pin_id);

ALTER TABLE ONLY street_smarts_old.knock_periods
    ADD CONSTRAINT knock_periods_pkey PRIMARY KEY (team_id, cell_id, season_id, period);

ALTER TABLE ONLY street_smarts_old.knocks
    ADD CONSTRAINT knocks_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts_old.outcomes
    ADD CONSTRAINT outcomes_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts_old.pin_notes
    ADD CONSTRAINT pin_notes_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts_old.pins
    ADD CONSTRAINT pins_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts_old.pins_temp
    ADD CONSTRAINT pins_temp_pkey PRIMARY KEY (id);

ALTER TABLE ONLY street_smarts_old.tmp_deleted_pins
    ADD CONSTRAINT tmp_deleted_pins_pkey PRIMARY KEY (id);

CREATE INDEX ecosure_recruiting_seasons_name_index ON alterra_production.ecosure_recruiting_seasons USING btree (name);

CREATE INDEX ecosure_recruits_is_signed_index ON alterra_production.ecosure_recruits USING btree (is_signed);

CREATE INDEX ecosure_users_active_index ON alterra_production.ecosure_users USING btree (active);

CREATE INDEX ecosure_users_deleted_index ON alterra_production.ecosure_users USING btree (deleted);

CREATE INDEX email_index ON alterra_production.ecosure_users USING btree (email);

CREATE INDEX experienced ON alterra_production.ecosure_contacts USING btree (experience);

CREATE INDEX group_id ON alterra_production.ecosure_users USING btree (group_id);

CREATE INDEX idx_first_name_last_name ON alterra_production.ecosure_contacts USING btree (first_name, last_name);

CREATE INDEX idx_recruits_user_id ON alterra_production.ecosure_recruits USING btree (user_id);

CREATE INDEX lead_id ON alterra_production.ecosure_contacts USING btree (lead_id);

CREATE INDEX office_id ON alterra_production.ecosure_users USING btree (office_id);

CREATE INDEX parent_id ON alterra_production.ecosure_users USING btree (parent_id);

CREATE INDEX recruiting_season_id ON alterra_production.ecosure_recruits USING btree (recruiting_season_id);

CREATE INDEX regional_manager_id_index ON alterra_production.ecosure_users USING btree (regional_manager_id);

CREATE INDEX scouts_recruiting_season_id ON alterra_production.ecosure_scouts USING btree (recruiting_season_id);

CREATE INDEX scouts_user_id ON alterra_production.ecosure_scouts USING btree (user_id);

CREATE INDEX signed_by_id ON alterra_production.ecosure_recruits USING btree (signed_by_id_back);

CREATE INDEX streetsmarts_app ON alterra_production.ecosure_users USING btree (active, deleted, per_rep_average, knock_active);

CREATE INDEX user_id ON alterra_production.ecosure_contacts USING btree (user_id);

CREATE INDEX billing__payments_created_at_index ON audit.billing__payments USING btree (created_at);

CREATE INDEX billing__payments_table_id_index ON audit.billing__payments USING btree (table_id);

CREATE INDEX field_operations__route_templates_created_at_idx ON audit.field_operations__route_templates USING btree (created_at);

CREATE INDEX field_operations__route_templates_created_by_idx ON audit.field_operations__route_templates USING btree (created_by);

CREATE INDEX field_operations__route_templates_deleted_by_idx ON audit.field_operations__route_templates USING btree (deleted_by);

CREATE INDEX field_operations__route_templates_table_id_idx ON audit.field_operations__route_templates USING btree (table_id);

CREATE INDEX field_operations__route_templates_updated_at_idx ON audit.field_operations__route_templates USING btree (updated_at);

CREATE INDEX field_operations__route_templates_updated_by_idx ON audit.field_operations__route_templates USING btree (updated_by);

CREATE INDEX idx_billing__decline_reasons_created_at ON audit.billing__decline_reasons USING btree (created_at);

CREATE INDEX idx_billing__decline_reasons_created_by ON audit.billing__decline_reasons USING btree (created_by);

CREATE INDEX idx_billing__decline_reasons_deleted_by ON audit.billing__decline_reasons USING btree (deleted_by);

CREATE INDEX idx_billing__decline_reasons_pestroutes_sync_status_pending_onl ON audit.billing__decline_reasons USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__decline_reasons_table_id ON audit.billing__decline_reasons USING btree (table_id);

CREATE INDEX idx_billing__decline_reasons_updated_at ON audit.billing__decline_reasons USING btree (updated_at);

CREATE INDEX idx_billing__decline_reasons_updated_by ON audit.billing__decline_reasons USING btree (updated_by);

CREATE INDEX idx_billing__default_autopay_payment_methods_created_at ON audit.billing__default_autopay_payment_methods USING btree (created_at);

CREATE INDEX idx_billing__default_autopay_payment_methods_created_by ON audit.billing__default_autopay_payment_methods USING btree (created_by);

CREATE INDEX idx_billing__default_autopay_payment_methods_deleted_by ON audit.billing__default_autopay_payment_methods USING btree (deleted_by);

CREATE INDEX idx_billing__default_autopay_payment_methods_pestroutes_sync_st ON audit.billing__default_autopay_payment_methods USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__default_autopay_payment_methods_table_id ON audit.billing__default_autopay_payment_methods USING btree (table_id);

CREATE INDEX idx_billing__default_autopay_payment_methods_updated_at ON audit.billing__default_autopay_payment_methods USING btree (updated_at);

CREATE INDEX idx_billing__default_autopay_payment_methods_updated_by ON audit.billing__default_autopay_payment_methods USING btree (updated_by);

CREATE INDEX idx_billing__distinct_payment_methods_created_at ON audit.billing__distinct_payment_methods USING btree (created_at);

CREATE INDEX idx_billing__distinct_payment_methods_created_by ON audit.billing__distinct_payment_methods USING btree (created_by);

CREATE INDEX idx_billing__distinct_payment_methods_deleted_by ON audit.billing__distinct_payment_methods USING btree (deleted_by);

CREATE INDEX idx_billing__distinct_payment_methods_pestroutes_sync_status_pe ON audit.billing__distinct_payment_methods USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__distinct_payment_methods_table_id ON audit.billing__distinct_payment_methods USING btree (table_id);

CREATE INDEX idx_billing__distinct_payment_methods_updated_at ON audit.billing__distinct_payment_methods USING btree (updated_at);

CREATE INDEX idx_billing__distinct_payment_methods_updated_by ON audit.billing__distinct_payment_methods USING btree (updated_by);

CREATE INDEX idx_billing__failed_jobs_created_at ON audit.billing__failed_jobs USING btree (created_at);

CREATE INDEX idx_billing__failed_jobs_created_by ON audit.billing__failed_jobs USING btree (created_by);

CREATE INDEX idx_billing__failed_jobs_deleted_by ON audit.billing__failed_jobs USING btree (deleted_by);

CREATE INDEX idx_billing__failed_jobs_pestroutes_sync_status_pending_only ON audit.billing__failed_jobs USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__failed_jobs_table_id ON audit.billing__failed_jobs USING btree (table_id);

CREATE INDEX idx_billing__failed_jobs_updated_at ON audit.billing__failed_jobs USING btree (updated_at);

CREATE INDEX idx_billing__failed_jobs_updated_by ON audit.billing__failed_jobs USING btree (updated_by);

CREATE INDEX idx_billing__failed_refund_payments_created_at ON audit.billing__failed_refund_payments USING btree (created_at);

CREATE INDEX idx_billing__failed_refund_payments_created_by ON audit.billing__failed_refund_payments USING btree (created_by);

CREATE INDEX idx_billing__failed_refund_payments_deleted_by ON audit.billing__failed_refund_payments USING btree (deleted_by);

CREATE INDEX idx_billing__failed_refund_payments_pestroutes_sync_status_pend ON audit.billing__failed_refund_payments USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__failed_refund_payments_table_id ON audit.billing__failed_refund_payments USING btree (table_id);

CREATE INDEX idx_billing__failed_refund_payments_updated_at ON audit.billing__failed_refund_payments USING btree (updated_at);

CREATE INDEX idx_billing__failed_refund_payments_updated_by ON audit.billing__failed_refund_payments USING btree (updated_by);

CREATE INDEX idx_billing__invoices_created_at ON audit.billing__invoices USING btree (created_at);

CREATE INDEX idx_billing__invoices_created_by ON audit.billing__invoices USING btree (created_by);

CREATE INDEX idx_billing__invoices_deleted_by ON audit.billing__invoices USING btree (deleted_by);

CREATE INDEX idx_billing__invoices_pestroutes_sync_status_pending_only ON audit.billing__invoices USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__invoices_table_id ON audit.billing__invoices USING btree (table_id);

CREATE INDEX idx_billing__invoices_updated_at ON audit.billing__invoices USING btree (updated_at);

CREATE INDEX idx_billing__invoices_updated_by ON audit.billing__invoices USING btree (updated_by);

CREATE INDEX idx_billing__ledger_created_at ON audit.billing__ledger USING btree (created_at);

CREATE INDEX idx_billing__ledger_created_by ON audit.billing__ledger USING btree (created_by);

CREATE INDEX idx_billing__ledger_deleted_by ON audit.billing__ledger USING btree (deleted_by);

CREATE INDEX idx_billing__ledger_pestroutes_sync_status_pending_only ON audit.billing__ledger USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__ledger_table_id ON audit.billing__ledger USING btree (table_id);

CREATE INDEX idx_billing__ledger_updated_at ON audit.billing__ledger USING btree (updated_at);

CREATE INDEX idx_billing__ledger_updated_by ON audit.billing__ledger USING btree (updated_by);

CREATE INDEX idx_billing__new_payments_with_last_four_created_at ON audit.billing__new_payments_with_last_four USING btree (created_at);

CREATE INDEX idx_billing__new_payments_with_last_four_created_by ON audit.billing__new_payments_with_last_four USING btree (created_by);

CREATE INDEX idx_billing__new_payments_with_last_four_deleted_by ON audit.billing__new_payments_with_last_four USING btree (deleted_by);

CREATE INDEX idx_billing__new_payments_with_last_four_pestroutes_sync_status ON audit.billing__new_payments_with_last_four USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__new_payments_with_last_four_table_id ON audit.billing__new_payments_with_last_four USING btree (table_id);

CREATE INDEX idx_billing__new_payments_with_last_four_updated_at ON audit.billing__new_payments_with_last_four USING btree (updated_at);

CREATE INDEX idx_billing__new_payments_with_last_four_updated_by ON audit.billing__new_payments_with_last_four USING btree (updated_by);

CREATE INDEX idx_billing__payment_methods_created_at ON audit.billing__payment_methods USING btree (created_at);

CREATE INDEX idx_billing__payment_methods_created_by ON audit.billing__payment_methods USING btree (created_by);

CREATE INDEX idx_billing__payment_methods_deleted_by ON audit.billing__payment_methods USING btree (deleted_by);

CREATE INDEX idx_billing__payment_methods_pestroutes_sync_status_pending_onl ON audit.billing__payment_methods USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__payment_methods_table_id ON audit.billing__payment_methods USING btree (table_id);

CREATE INDEX idx_billing__payment_methods_updated_at ON audit.billing__payment_methods USING btree (updated_at);

CREATE INDEX idx_billing__payment_methods_updated_by ON audit.billing__payment_methods USING btree (updated_by);

CREATE INDEX idx_billing__payment_update_last4_log_created_at ON audit.billing__payment_update_last4_log USING btree (created_at);

CREATE INDEX idx_billing__payment_update_last4_log_created_by ON audit.billing__payment_update_last4_log USING btree (created_by);

CREATE INDEX idx_billing__payment_update_last4_log_deleted_by ON audit.billing__payment_update_last4_log USING btree (deleted_by);

CREATE INDEX idx_billing__payment_update_last4_log_pestroutes_sync_status_pe ON audit.billing__payment_update_last4_log USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__payment_update_last4_log_table_id ON audit.billing__payment_update_last4_log USING btree (table_id);

CREATE INDEX idx_billing__payment_update_last4_log_updated_at ON audit.billing__payment_update_last4_log USING btree (updated_at);

CREATE INDEX idx_billing__payment_update_last4_log_updated_by ON audit.billing__payment_update_last4_log USING btree (updated_by);

CREATE INDEX idx_billing__payment_update_pestroutes_created_by_crm_log_creat ON audit.billing__payment_update_pestroutes_created_by_crm_log USING btree (created_at);

CREATE INDEX idx_billing__payment_update_pestroutes_created_by_crm_log_delet ON audit.billing__payment_update_pestroutes_created_by_crm_log USING btree (deleted_by);

CREATE INDEX idx_billing__payment_update_pestroutes_created_by_crm_log_pestr ON audit.billing__payment_update_pestroutes_created_by_crm_log USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__payment_update_pestroutes_created_by_crm_log_table ON audit.billing__payment_update_pestroutes_created_by_crm_log USING btree (table_id);

CREATE INDEX idx_billing__payment_update_pestroutes_created_by_crm_log_updat ON audit.billing__payment_update_pestroutes_created_by_crm_log USING btree (updated_at);

CREATE INDEX idx_billing__payments_created_at ON audit.billing__payments USING btree (created_at);

CREATE INDEX idx_billing__payments_created_by ON audit.billing__payments USING btree (created_by);

CREATE INDEX idx_billing__payments_deleted_by ON audit.billing__payments USING btree (deleted_by);

CREATE INDEX idx_billing__payments_pestroutes_sync_status_pending_only ON audit.billing__payments USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__payments_table_id ON audit.billing__payments USING btree (table_id);

CREATE INDEX idx_billing__payments_updated_at ON audit.billing__payments USING btree (updated_at);

CREATE INDEX idx_billing__payments_updated_by ON audit.billing__payments USING btree (updated_by);

CREATE INDEX idx_billing__scheduled_payment_statuses_created_at ON audit.billing__scheduled_payment_statuses USING btree (created_at);

CREATE INDEX idx_billing__scheduled_payment_statuses_created_by ON audit.billing__scheduled_payment_statuses USING btree (created_by);

CREATE INDEX idx_billing__scheduled_payment_statuses_deleted_by ON audit.billing__scheduled_payment_statuses USING btree (deleted_by);

CREATE INDEX idx_billing__scheduled_payment_statuses_pestroutes_sync_status_ ON audit.billing__scheduled_payment_statuses USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__scheduled_payment_statuses_table_id ON audit.billing__scheduled_payment_statuses USING btree (table_id);

CREATE INDEX idx_billing__scheduled_payment_statuses_updated_at ON audit.billing__scheduled_payment_statuses USING btree (updated_at);

CREATE INDEX idx_billing__scheduled_payment_statuses_updated_by ON audit.billing__scheduled_payment_statuses USING btree (updated_by);

CREATE INDEX idx_billing__scheduled_payment_triggers_created_at ON audit.billing__scheduled_payment_triggers USING btree (created_at);

CREATE INDEX idx_billing__scheduled_payment_triggers_created_by ON audit.billing__scheduled_payment_triggers USING btree (created_by);

CREATE INDEX idx_billing__scheduled_payment_triggers_deleted_by ON audit.billing__scheduled_payment_triggers USING btree (deleted_by);

CREATE INDEX idx_billing__scheduled_payment_triggers_pestroutes_sync_status_ ON audit.billing__scheduled_payment_triggers USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__scheduled_payment_triggers_table_id ON audit.billing__scheduled_payment_triggers USING btree (table_id);

CREATE INDEX idx_billing__scheduled_payment_triggers_updated_at ON audit.billing__scheduled_payment_triggers USING btree (updated_at);

CREATE INDEX idx_billing__scheduled_payment_triggers_updated_by ON audit.billing__scheduled_payment_triggers USING btree (updated_by);

CREATE INDEX idx_billing__scheduled_payments_created_at ON audit.billing__scheduled_payments USING btree (created_at);

CREATE INDEX idx_billing__scheduled_payments_created_by ON audit.billing__scheduled_payments USING btree (created_by);

CREATE INDEX idx_billing__scheduled_payments_deleted_by ON audit.billing__scheduled_payments USING btree (deleted_by);

CREATE INDEX idx_billing__scheduled_payments_pestroutes_sync_status_pending_ ON audit.billing__scheduled_payments USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__scheduled_payments_table_id ON audit.billing__scheduled_payments USING btree (table_id);

CREATE INDEX idx_billing__scheduled_payments_updated_at ON audit.billing__scheduled_payments USING btree (updated_at);

CREATE INDEX idx_billing__scheduled_payments_updated_by ON audit.billing__scheduled_payments USING btree (updated_by);

CREATE INDEX idx_billing__subscription_autopay_payment_methods_created_at ON audit.billing__subscription_autopay_payment_methods USING btree (created_at);

CREATE INDEX idx_billing__subscription_autopay_payment_methods_created_by ON audit.billing__subscription_autopay_payment_methods USING btree (created_by);

CREATE INDEX idx_billing__subscription_autopay_payment_methods_deleted_by ON audit.billing__subscription_autopay_payment_methods USING btree (deleted_by);

CREATE INDEX idx_billing__subscription_autopay_payment_methods_pestroutes_sy ON audit.billing__subscription_autopay_payment_methods USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__subscription_autopay_payment_methods_table_id ON audit.billing__subscription_autopay_payment_methods USING btree (table_id);

CREATE INDEX idx_billing__subscription_autopay_payment_methods_updated_at ON audit.billing__subscription_autopay_payment_methods USING btree (updated_at);

CREATE INDEX idx_billing__subscription_autopay_payment_methods_updated_by ON audit.billing__subscription_autopay_payment_methods USING btree (updated_by);

CREATE INDEX idx_billing__suspend_reasons_created_at ON audit.billing__suspend_reasons USING btree (created_at);

CREATE INDEX idx_billing__suspend_reasons_created_by ON audit.billing__suspend_reasons USING btree (created_by);

CREATE INDEX idx_billing__suspend_reasons_deleted_by ON audit.billing__suspend_reasons USING btree (deleted_by);

CREATE INDEX idx_billing__suspend_reasons_pestroutes_sync_status_pending_onl ON audit.billing__suspend_reasons USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_billing__suspend_reasons_table_id ON audit.billing__suspend_reasons USING btree (table_id);

CREATE INDEX idx_billing__suspend_reasons_updated_at ON audit.billing__suspend_reasons USING btree (updated_at);

CREATE INDEX idx_billing__suspend_reasons_updated_by ON audit.billing__suspend_reasons USING btree (updated_by);

CREATE INDEX idx_billing_payments_test_pestroutes_sync_status ON audit.billing__test_payments USING btree (pestroutes_sync_status);

CREATE INDEX idx_customer__accounts_created_at ON audit.customer__accounts USING btree (created_at);

CREATE INDEX idx_customer__accounts_created_by ON audit.customer__accounts USING btree (created_by);

CREATE INDEX idx_customer__accounts_deleted_by ON audit.customer__accounts USING btree (deleted_by);

CREATE INDEX idx_customer__accounts_pestroutes_sync_status_pending_only ON audit.customer__accounts USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_customer__accounts_table_id ON audit.customer__accounts USING btree (table_id);

CREATE INDEX idx_customer__accounts_updated_at ON audit.customer__accounts USING btree (updated_at);

CREATE INDEX idx_customer__accounts_updated_by ON audit.customer__accounts USING btree (updated_by);

CREATE INDEX idx_customer__addresses_created_at ON audit.customer__addresses USING btree (created_at);

CREATE INDEX idx_customer__addresses_created_by ON audit.customer__addresses USING btree (created_by);

CREATE INDEX idx_customer__addresses_deleted_by ON audit.customer__addresses USING btree (deleted_by);

CREATE INDEX idx_customer__addresses_pestroutes_sync_status_pending_only ON audit.customer__addresses USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_customer__addresses_table_id ON audit.customer__addresses USING btree (table_id);

CREATE INDEX idx_customer__addresses_updated_at ON audit.customer__addresses USING btree (updated_at);

CREATE INDEX idx_customer__addresses_updated_by ON audit.customer__addresses USING btree (updated_by);

CREATE INDEX idx_customer__contacts_created_at ON audit.customer__contacts USING btree (created_at);

CREATE INDEX idx_customer__contacts_created_by ON audit.customer__contacts USING btree (created_by);

CREATE INDEX idx_customer__contacts_deleted_by ON audit.customer__contacts USING btree (deleted_by);

CREATE INDEX idx_customer__contacts_pestroutes_sync_status_pending_only ON audit.customer__contacts USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_customer__contacts_table_id ON audit.customer__contacts USING btree (table_id);

CREATE INDEX idx_customer__contacts_updated_at ON audit.customer__contacts USING btree (updated_at);

CREATE INDEX idx_customer__contacts_updated_by ON audit.customer__contacts USING btree (updated_by);

CREATE INDEX idx_customer__contracts_created_at ON audit.customer__contracts USING btree (created_at);

CREATE INDEX idx_customer__contracts_created_by ON audit.customer__contracts USING btree (created_by);

CREATE INDEX idx_customer__contracts_deleted_by ON audit.customer__contracts USING btree (deleted_by);

CREATE INDEX idx_customer__contracts_pestroutes_sync_status_pending_only ON audit.customer__contracts USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_customer__contracts_table_id ON audit.customer__contracts USING btree (table_id);

CREATE INDEX idx_customer__contracts_updated_at ON audit.customer__contracts USING btree (updated_at);

CREATE INDEX idx_customer__contracts_updated_by ON audit.customer__contracts USING btree (updated_by);

CREATE INDEX idx_customer__documents_created_at ON audit.customer__documents USING btree (created_at);

CREATE INDEX idx_customer__documents_created_by ON audit.customer__documents USING btree (created_by);

CREATE INDEX idx_customer__documents_deleted_by ON audit.customer__documents USING btree (deleted_by);

CREATE INDEX idx_customer__documents_pestroutes_sync_status_pending_only ON audit.customer__documents USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_customer__documents_table_id ON audit.customer__documents USING btree (table_id);

CREATE INDEX idx_customer__documents_updated_at ON audit.customer__documents USING btree (updated_at);

CREATE INDEX idx_customer__documents_updated_by ON audit.customer__documents USING btree (updated_by);

CREATE INDEX idx_customer__forms_created_at ON audit.customer__forms USING btree (created_at);

CREATE INDEX idx_customer__forms_created_by ON audit.customer__forms USING btree (created_by);

CREATE INDEX idx_customer__forms_deleted_by ON audit.customer__forms USING btree (deleted_by);

CREATE INDEX idx_customer__forms_pestroutes_sync_status_pending_only ON audit.customer__forms USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_customer__forms_table_id ON audit.customer__forms USING btree (table_id);

CREATE INDEX idx_customer__forms_updated_at ON audit.customer__forms USING btree (updated_at);

CREATE INDEX idx_customer__forms_updated_by ON audit.customer__forms USING btree (updated_by);

CREATE INDEX idx_customer__notes_created_at ON audit.customer__notes USING btree (created_at);

CREATE INDEX idx_customer__notes_created_by ON audit.customer__notes USING btree (created_by);

CREATE INDEX idx_customer__notes_deleted_by ON audit.customer__notes USING btree (deleted_by);

CREATE INDEX idx_customer__notes_pestroutes_sync_status_pending_only ON audit.customer__notes USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_customer__notes_table_id ON audit.customer__notes USING btree (table_id);

CREATE INDEX idx_customer__notes_updated_at ON audit.customer__notes USING btree (updated_at);

CREATE INDEX idx_customer__notes_updated_by ON audit.customer__notes USING btree (updated_by);

CREATE INDEX idx_customer__subscriptions_created_at ON audit.customer__subscriptions USING btree (created_at);

CREATE INDEX idx_customer__subscriptions_created_by ON audit.customer__subscriptions USING btree (created_by);

CREATE INDEX idx_customer__subscriptions_deleted_by ON audit.customer__subscriptions USING btree (deleted_by);

CREATE INDEX idx_customer__subscriptions_pestroutes_sync_status_pending_only ON audit.customer__subscriptions USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_customer__subscriptions_table_id ON audit.customer__subscriptions USING btree (table_id);

CREATE INDEX idx_customer__subscriptions_updated_at ON audit.customer__subscriptions USING btree (updated_at);

CREATE INDEX idx_customer__subscriptions_updated_by ON audit.customer__subscriptions USING btree (updated_by);

CREATE INDEX idx_db_user_name ON audit.billing__payments USING btree (db_user_name);

CREATE INDEX idx_dbe_test__test_tbl_created_at ON audit.dbe_test__test_tbl USING btree (created_at);

CREATE INDEX idx_dbe_test__test_tbl_created_by ON audit.dbe_test__test_tbl USING btree (created_by);

CREATE INDEX idx_dbe_test__test_tbl_deleted_by ON audit.dbe_test__test_tbl USING btree (deleted_by);

CREATE INDEX idx_dbe_test__test_tbl_pestroutes_sync_status_pending_only ON audit.dbe_test__test_tbl USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_dbe_test__test_tbl_table_id ON audit.dbe_test__test_tbl USING btree (table_id);

CREATE INDEX idx_dbe_test__test_tbl_updated_at ON audit.dbe_test__test_tbl USING btree (updated_at);

CREATE INDEX idx_dbe_test__test_tbl_updated_by ON audit.dbe_test__test_tbl USING btree (updated_by);

CREATE INDEX idx_field_operations__appointments_created_at ON audit.field_operations__appointments USING btree (created_at);

CREATE INDEX idx_field_operations__appointments_created_by ON audit.field_operations__appointments USING btree (created_by);

CREATE INDEX idx_field_operations__appointments_deleted_by ON audit.field_operations__appointments USING btree (deleted_by);

CREATE INDEX idx_field_operations__appointments_pestroutes_sync_status_pendi ON audit.field_operations__appointments USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__appointments_table_id ON audit.field_operations__appointments USING btree (table_id);

CREATE INDEX idx_field_operations__appointments_updated_at ON audit.field_operations__appointments USING btree (updated_at);

CREATE INDEX idx_field_operations__appointments_updated_by ON audit.field_operations__appointments USING btree (updated_by);

CREATE INDEX idx_field_operations__aro_users_created_at ON audit.field_operations__aro_users USING btree (created_at);

CREATE INDEX idx_field_operations__aro_users_created_by ON audit.field_operations__aro_users USING btree (created_by);

CREATE INDEX idx_field_operations__aro_users_deleted_by ON audit.field_operations__aro_users USING btree (deleted_by);

CREATE INDEX idx_field_operations__aro_users_pestroutes_sync_status_pending_ ON audit.field_operations__aro_users USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__aro_users_table_id ON audit.field_operations__aro_users USING btree (table_id);

CREATE INDEX idx_field_operations__aro_users_updated_at ON audit.field_operations__aro_users USING btree (updated_at);

CREATE INDEX idx_field_operations__aro_users_updated_by ON audit.field_operations__aro_users USING btree (updated_by);

CREATE INDEX idx_field_operations__customer_property_details_created_at ON audit.field_operations__customer_property_details USING btree (created_at);

CREATE INDEX idx_field_operations__customer_property_details_created_by ON audit.field_operations__customer_property_details USING btree (created_by);

CREATE INDEX idx_field_operations__customer_property_details_deleted_by ON audit.field_operations__customer_property_details USING btree (deleted_by);

CREATE INDEX idx_field_operations__customer_property_details_pestroutes_sync ON audit.field_operations__customer_property_details USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__customer_property_details_table_id ON audit.field_operations__customer_property_details USING btree (table_id);

CREATE INDEX idx_field_operations__customer_property_details_updated_at ON audit.field_operations__customer_property_details USING btree (updated_at);

CREATE INDEX idx_field_operations__customer_property_details_updated_by ON audit.field_operations__customer_property_details USING btree (updated_by);

CREATE INDEX idx_field_operations__monthly_financial_reports_created_at ON audit.field_operations__monthly_financial_reports USING btree (created_at);

CREATE INDEX idx_field_operations__monthly_financial_reports_created_by ON audit.field_operations__monthly_financial_reports USING btree (created_by);

CREATE INDEX idx_field_operations__monthly_financial_reports_deleted_by ON audit.field_operations__monthly_financial_reports USING btree (deleted_by);

CREATE INDEX idx_field_operations__monthly_financial_reports_pestroutes_sync ON audit.field_operations__monthly_financial_reports USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__monthly_financial_reports_table_id ON audit.field_operations__monthly_financial_reports USING btree (table_id);

CREATE INDEX idx_field_operations__monthly_financial_reports_updated_at ON audit.field_operations__monthly_financial_reports USING btree (updated_at);

CREATE INDEX idx_field_operations__monthly_financial_reports_updated_by ON audit.field_operations__monthly_financial_reports USING btree (updated_by);

CREATE INDEX idx_field_operations__notification_recipient_type_created_at ON audit.field_operations__notification_recipient_type USING btree (created_at);

CREATE INDEX idx_field_operations__notification_recipient_type_created_by ON audit.field_operations__notification_recipient_type USING btree (created_by);

CREATE INDEX idx_field_operations__notification_recipient_type_deleted_by ON audit.field_operations__notification_recipient_type USING btree (deleted_by);

CREATE INDEX idx_field_operations__notification_recipient_type_pestroutes_sy ON audit.field_operations__notification_recipient_type USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__notification_recipient_type_table_id ON audit.field_operations__notification_recipient_type USING btree (table_id);

CREATE INDEX idx_field_operations__notification_recipient_type_updated_at ON audit.field_operations__notification_recipient_type USING btree (updated_at);

CREATE INDEX idx_field_operations__notification_recipient_type_updated_by ON audit.field_operations__notification_recipient_type USING btree (updated_by);

CREATE INDEX idx_field_operations__notification_recipients_created_at ON audit.field_operations__notification_recipients USING btree (created_at);

CREATE INDEX idx_field_operations__notification_recipients_created_by ON audit.field_operations__notification_recipients USING btree (created_by);

CREATE INDEX idx_field_operations__notification_recipients_deleted_by ON audit.field_operations__notification_recipients USING btree (deleted_by);

CREATE INDEX idx_field_operations__notification_recipients_pestroutes_sync_s ON audit.field_operations__notification_recipients USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__notification_recipients_table_id ON audit.field_operations__notification_recipients USING btree (table_id);

CREATE INDEX idx_field_operations__notification_recipients_updated_at ON audit.field_operations__notification_recipients USING btree (updated_at);

CREATE INDEX idx_field_operations__notification_recipients_updated_by ON audit.field_operations__notification_recipients USING btree (updated_by);

CREATE INDEX idx_field_operations__notification_types_created_at ON audit.field_operations__notification_types USING btree (created_at);

CREATE INDEX idx_field_operations__notification_types_created_by ON audit.field_operations__notification_types USING btree (created_by);

CREATE INDEX idx_field_operations__notification_types_deleted_by ON audit.field_operations__notification_types USING btree (deleted_by);

CREATE INDEX idx_field_operations__notification_types_pestroutes_sync_status ON audit.field_operations__notification_types USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__notification_types_table_id ON audit.field_operations__notification_types USING btree (table_id);

CREATE INDEX idx_field_operations__notification_types_updated_at ON audit.field_operations__notification_types USING btree (updated_at);

CREATE INDEX idx_field_operations__notification_types_updated_by ON audit.field_operations__notification_types USING btree (updated_by);

CREATE INDEX idx_field_operations__route_details_created_at ON audit.field_operations__route_details USING btree (created_at);

CREATE INDEX idx_field_operations__route_details_created_by ON audit.field_operations__route_details USING btree (created_by);

CREATE INDEX idx_field_operations__route_details_deleted_by ON audit.field_operations__route_details USING btree (deleted_by);

CREATE INDEX idx_field_operations__route_details_pestroutes_sync_status_pend ON audit.field_operations__route_details USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__route_details_table_id ON audit.field_operations__route_details USING btree (table_id);

CREATE INDEX idx_field_operations__route_details_updated_at ON audit.field_operations__route_details USING btree (updated_at);

CREATE INDEX idx_field_operations__route_details_updated_by ON audit.field_operations__route_details USING btree (updated_by);

CREATE INDEX idx_field_operations__route_geometries_created_at ON audit.field_operations__route_geometries USING btree (created_at);

CREATE INDEX idx_field_operations__route_geometries_created_by ON audit.field_operations__route_geometries USING btree (created_by);

CREATE INDEX idx_field_operations__route_geometries_deleted_by ON audit.field_operations__route_geometries USING btree (deleted_by);

CREATE INDEX idx_field_operations__route_geometries_pestroutes_sync_status_p ON audit.field_operations__route_geometries USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__route_geometries_table_id ON audit.field_operations__route_geometries USING btree (table_id);

CREATE INDEX idx_field_operations__route_geometries_updated_at ON audit.field_operations__route_geometries USING btree (updated_at);

CREATE INDEX idx_field_operations__route_geometries_updated_by ON audit.field_operations__route_geometries USING btree (updated_by);

CREATE INDEX idx_field_operations__route_templates_created_at ON audit.field_operations__route_templates USING btree (created_at);

CREATE INDEX idx_field_operations__route_templates_created_by ON audit.field_operations__route_templates USING btree (created_by);

CREATE INDEX idx_field_operations__route_templates_deleted_by ON audit.field_operations__route_templates USING btree (deleted_by);

CREATE INDEX idx_field_operations__route_templates_pestroutes_sync_status_pe ON audit.field_operations__route_templates USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__route_templates_table_id ON audit.field_operations__route_templates USING btree (table_id);

CREATE INDEX idx_field_operations__route_templates_updated_at ON audit.field_operations__route_templates USING btree (updated_at);

CREATE INDEX idx_field_operations__route_templates_updated_by ON audit.field_operations__route_templates USING btree (updated_by);

CREATE INDEX idx_field_operations__routes_created_at ON audit.field_operations__routes USING btree (created_at);

CREATE INDEX idx_field_operations__routes_created_by ON audit.field_operations__routes USING btree (created_by);

CREATE INDEX idx_field_operations__routes_deleted_by ON audit.field_operations__routes USING btree (deleted_by);

CREATE INDEX idx_field_operations__routes_pestroutes_sync_status_pending_onl ON audit.field_operations__routes USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__routes_table_id ON audit.field_operations__routes USING btree (table_id);

CREATE INDEX idx_field_operations__routes_updated_at ON audit.field_operations__routes USING btree (updated_at);

CREATE INDEX idx_field_operations__routes_updated_by ON audit.field_operations__routes USING btree (updated_by);

CREATE INDEX idx_field_operations__scheduled_route_details_created_at ON audit.field_operations__scheduled_route_details USING btree (created_at);

CREATE INDEX idx_field_operations__scheduled_route_details_created_by ON audit.field_operations__scheduled_route_details USING btree (created_by);

CREATE INDEX idx_field_operations__scheduled_route_details_deleted_by ON audit.field_operations__scheduled_route_details USING btree (deleted_by);

CREATE INDEX idx_field_operations__scheduled_route_details_pestroutes_sync_s ON audit.field_operations__scheduled_route_details USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__scheduled_route_details_table_id ON audit.field_operations__scheduled_route_details USING btree (table_id);

CREATE INDEX idx_field_operations__scheduled_route_details_updated_at ON audit.field_operations__scheduled_route_details USING btree (updated_at);

CREATE INDEX idx_field_operations__scheduled_route_details_updated_by ON audit.field_operations__scheduled_route_details USING btree (updated_by);

CREATE INDEX idx_field_operations__scheduling_states_created_at ON audit.field_operations__scheduling_states USING btree (created_at);

CREATE INDEX idx_field_operations__scheduling_states_created_by ON audit.field_operations__scheduling_states USING btree (created_by);

CREATE INDEX idx_field_operations__scheduling_states_deleted_by ON audit.field_operations__scheduling_states USING btree (deleted_by);

CREATE INDEX idx_field_operations__scheduling_states_pestroutes_sync_status_ ON audit.field_operations__scheduling_states USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__scheduling_states_table_id ON audit.field_operations__scheduling_states USING btree (table_id);

CREATE INDEX idx_field_operations__scheduling_states_updated_at ON audit.field_operations__scheduling_states USING btree (updated_at);

CREATE INDEX idx_field_operations__scheduling_states_updated_by ON audit.field_operations__scheduling_states USING btree (updated_by);

CREATE INDEX idx_field_operations__service_types_created_at ON audit.field_operations__service_types USING btree (created_at);

CREATE INDEX idx_field_operations__service_types_created_by ON audit.field_operations__service_types USING btree (created_by);

CREATE INDEX idx_field_operations__service_types_deleted_by ON audit.field_operations__service_types USING btree (deleted_by);

CREATE INDEX idx_field_operations__service_types_pestroutes_sync_status_pend ON audit.field_operations__service_types USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__service_types_table_id ON audit.field_operations__service_types USING btree (table_id);

CREATE INDEX idx_field_operations__service_types_updated_at ON audit.field_operations__service_types USING btree (updated_at);

CREATE INDEX idx_field_operations__service_types_updated_by ON audit.field_operations__service_types USING btree (updated_by);

CREATE INDEX idx_field_operations__serviced_route_details_created_at ON audit.field_operations__serviced_route_details USING btree (created_at);

CREATE INDEX idx_field_operations__serviced_route_details_created_by ON audit.field_operations__serviced_route_details USING btree (created_by);

CREATE INDEX idx_field_operations__serviced_route_details_deleted_by ON audit.field_operations__serviced_route_details USING btree (deleted_by);

CREATE INDEX idx_field_operations__serviced_route_details_pestroutes_sync_st ON audit.field_operations__serviced_route_details USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__serviced_route_details_table_id ON audit.field_operations__serviced_route_details USING btree (table_id);

CREATE INDEX idx_field_operations__serviced_route_details_updated_at ON audit.field_operations__serviced_route_details USING btree (updated_at);

CREATE INDEX idx_field_operations__serviced_route_details_updated_by ON audit.field_operations__serviced_route_details USING btree (updated_by);

CREATE INDEX idx_field_operations__treatment_states_created_at ON audit.field_operations__treatment_states USING btree (created_at);

CREATE INDEX idx_field_operations__treatment_states_created_by ON audit.field_operations__treatment_states USING btree (created_by);

CREATE INDEX idx_field_operations__treatment_states_deleted_by ON audit.field_operations__treatment_states USING btree (deleted_by);

CREATE INDEX idx_field_operations__treatment_states_pestroutes_sync_status_p ON audit.field_operations__treatment_states USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_field_operations__treatment_states_table_id ON audit.field_operations__treatment_states USING btree (table_id);

CREATE INDEX idx_field_operations__treatment_states_updated_at ON audit.field_operations__treatment_states USING btree (updated_at);

CREATE INDEX idx_field_operations__treatment_states_updated_by ON audit.field_operations__treatment_states USING btree (updated_by);

CREATE INDEX idx_notifications__cache_created_at ON audit.notifications__cache USING btree (created_at);

CREATE INDEX idx_notifications__cache_created_by ON audit.notifications__cache USING btree (created_by);

CREATE INDEX idx_notifications__cache_deleted_by ON audit.notifications__cache USING btree (deleted_by);

CREATE INDEX idx_notifications__cache_pestroutes_sync_status_pending_only ON audit.notifications__cache USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_notifications__cache_table_id ON audit.notifications__cache USING btree (table_id);

CREATE INDEX idx_notifications__cache_updated_at ON audit.notifications__cache USING btree (updated_at);

CREATE INDEX idx_notifications__cache_updated_by ON audit.notifications__cache USING btree (updated_by);

CREATE INDEX idx_notifications__headshot_paths_created_at ON audit.notifications__headshot_paths USING btree (created_at);

CREATE INDEX idx_notifications__headshot_paths_created_by ON audit.notifications__headshot_paths USING btree (created_by);

CREATE INDEX idx_notifications__headshot_paths_deleted_by ON audit.notifications__headshot_paths USING btree (deleted_by);

CREATE INDEX idx_notifications__headshot_paths_pestroutes_sync_status_pendin ON audit.notifications__headshot_paths USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_notifications__headshot_paths_table_id ON audit.notifications__headshot_paths USING btree (table_id);

CREATE INDEX idx_notifications__headshot_paths_updated_at ON audit.notifications__headshot_paths USING btree (updated_at);

CREATE INDEX idx_notifications__headshot_paths_updated_by ON audit.notifications__headshot_paths USING btree (updated_by);

CREATE INDEX idx_notifications__incoming_mms_messages_created_at ON audit.notifications__incoming_mms_messages USING btree (created_at);

CREATE INDEX idx_notifications__incoming_mms_messages_created_by ON audit.notifications__incoming_mms_messages USING btree (created_by);

CREATE INDEX idx_notifications__incoming_mms_messages_deleted_by ON audit.notifications__incoming_mms_messages USING btree (deleted_by);

CREATE INDEX idx_notifications__incoming_mms_messages_pestroutes_sync_status ON audit.notifications__incoming_mms_messages USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_notifications__incoming_mms_messages_table_id ON audit.notifications__incoming_mms_messages USING btree (table_id);

CREATE INDEX idx_notifications__incoming_mms_messages_updated_at ON audit.notifications__incoming_mms_messages USING btree (updated_at);

CREATE INDEX idx_notifications__incoming_mms_messages_updated_by ON audit.notifications__incoming_mms_messages USING btree (updated_by);

CREATE INDEX idx_notifications__logs_created_at ON audit.notifications__logs USING btree (created_at);

CREATE INDEX idx_notifications__logs_created_by ON audit.notifications__logs USING btree (created_by);

CREATE INDEX idx_notifications__logs_deleted_by ON audit.notifications__logs USING btree (deleted_by);

CREATE INDEX idx_notifications__logs_email_created_at ON audit.notifications__logs_email USING btree (created_at);

CREATE INDEX idx_notifications__logs_email_created_by ON audit.notifications__logs_email USING btree (created_by);

CREATE INDEX idx_notifications__logs_email_deleted_by ON audit.notifications__logs_email USING btree (deleted_by);

CREATE INDEX idx_notifications__logs_email_pestroutes_sync_status_pending_on ON audit.notifications__logs_email USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_notifications__logs_email_table_id ON audit.notifications__logs_email USING btree (table_id);

CREATE INDEX idx_notifications__logs_email_updated_at ON audit.notifications__logs_email USING btree (updated_at);

CREATE INDEX idx_notifications__logs_email_updated_by ON audit.notifications__logs_email USING btree (updated_by);

CREATE INDEX idx_notifications__logs_pestroutes_sync_status_pending_only ON audit.notifications__logs USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_notifications__logs_table_id ON audit.notifications__logs USING btree (table_id);

CREATE INDEX idx_notifications__logs_updated_at ON audit.notifications__logs USING btree (updated_at);

CREATE INDEX idx_notifications__logs_updated_by ON audit.notifications__logs USING btree (updated_by);

CREATE INDEX idx_notifications__notifications_sent_batches_created_at ON audit.notifications__notifications_sent_batches USING btree (created_at);

CREATE INDEX idx_notifications__notifications_sent_batches_created_by ON audit.notifications__notifications_sent_batches USING btree (created_by);

CREATE INDEX idx_notifications__notifications_sent_batches_deleted_by ON audit.notifications__notifications_sent_batches USING btree (deleted_by);

CREATE INDEX idx_notifications__notifications_sent_batches_pestroutes_sync_s ON audit.notifications__notifications_sent_batches USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_notifications__notifications_sent_batches_table_id ON audit.notifications__notifications_sent_batches USING btree (table_id);

CREATE INDEX idx_notifications__notifications_sent_batches_updated_at ON audit.notifications__notifications_sent_batches USING btree (updated_at);

CREATE INDEX idx_notifications__notifications_sent_batches_updated_by ON audit.notifications__notifications_sent_batches USING btree (updated_by);

CREATE INDEX idx_notifications__sms_messages_created_at ON audit.notifications__sms_messages USING btree (created_at);

CREATE INDEX idx_notifications__sms_messages_created_by ON audit.notifications__sms_messages USING btree (created_by);

CREATE INDEX idx_notifications__sms_messages_deleted_by ON audit.notifications__sms_messages USING btree (deleted_by);

CREATE INDEX idx_notifications__sms_messages_media_created_at ON audit.notifications__sms_messages_media USING btree (created_at);

CREATE INDEX idx_notifications__sms_messages_media_created_by ON audit.notifications__sms_messages_media USING btree (created_by);

CREATE INDEX idx_notifications__sms_messages_media_deleted_by ON audit.notifications__sms_messages_media USING btree (deleted_by);

CREATE INDEX idx_notifications__sms_messages_media_pestroutes_sync_status_pe ON audit.notifications__sms_messages_media USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_notifications__sms_messages_media_table_id ON audit.notifications__sms_messages_media USING btree (table_id);

CREATE INDEX idx_notifications__sms_messages_media_updated_at ON audit.notifications__sms_messages_media USING btree (updated_at);

CREATE INDEX idx_notifications__sms_messages_media_updated_by ON audit.notifications__sms_messages_media USING btree (updated_by);

CREATE INDEX idx_notifications__sms_messages_pestroutes_sync_status_pending_ ON audit.notifications__sms_messages USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_notifications__sms_messages_table_id ON audit.notifications__sms_messages USING btree (table_id);

CREATE INDEX idx_notifications__sms_messages_updated_at ON audit.notifications__sms_messages USING btree (updated_at);

CREATE INDEX idx_notifications__sms_messages_updated_by ON audit.notifications__sms_messages USING btree (updated_by);

CREATE INDEX idx_operation ON audit.billing__payments USING btree (operation);

CREATE INDEX idx_pestroutes__etl_writer_queue_created_at ON audit.pestroutes__etl_writer_queue USING btree (created_at);

CREATE INDEX idx_pestroutes__etl_writer_queue_created_by ON audit.pestroutes__etl_writer_queue USING btree (created_by);

CREATE INDEX idx_pestroutes__etl_writer_queue_deleted_by ON audit.pestroutes__etl_writer_queue USING btree (deleted_by);

CREATE INDEX idx_pestroutes__etl_writer_queue_log_created_at ON audit.pestroutes__etl_writer_queue_log USING btree (created_at);

CREATE INDEX idx_pestroutes__etl_writer_queue_log_created_by ON audit.pestroutes__etl_writer_queue_log USING btree (created_by);

CREATE INDEX idx_pestroutes__etl_writer_queue_log_deleted_by ON audit.pestroutes__etl_writer_queue_log USING btree (deleted_by);

CREATE INDEX idx_pestroutes__etl_writer_queue_log_pestroutes_sync_status_pen ON audit.pestroutes__etl_writer_queue_log USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_pestroutes__etl_writer_queue_log_table_id ON audit.pestroutes__etl_writer_queue_log USING btree (table_id);

CREATE INDEX idx_pestroutes__etl_writer_queue_log_updated_at ON audit.pestroutes__etl_writer_queue_log USING btree (updated_at);

CREATE INDEX idx_pestroutes__etl_writer_queue_log_updated_by ON audit.pestroutes__etl_writer_queue_log USING btree (updated_by);

CREATE INDEX idx_pestroutes__etl_writer_queue_pestroutes_sync_status_pending ON audit.pestroutes__etl_writer_queue USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_pestroutes__etl_writer_queue_table_id ON audit.pestroutes__etl_writer_queue USING btree (table_id);

CREATE INDEX idx_pestroutes__etl_writer_queue_updated_at ON audit.pestroutes__etl_writer_queue USING btree (updated_at);

CREATE INDEX idx_pestroutes__etl_writer_queue_updated_by ON audit.pestroutes__etl_writer_queue USING btree (updated_by);

CREATE INDEX idx_pestroutes__queue_created_at ON audit.pestroutes__queue USING btree (created_at);

CREATE INDEX idx_pestroutes__queue_created_by ON audit.pestroutes__queue USING btree (created_by);

CREATE INDEX idx_pestroutes__queue_deleted_by ON audit.pestroutes__queue USING btree (deleted_by);

CREATE INDEX idx_pestroutes__queue_log_created_at ON audit.pestroutes__queue_log USING btree (created_at);

CREATE INDEX idx_pestroutes__queue_log_created_by ON audit.pestroutes__queue_log USING btree (created_by);

CREATE INDEX idx_pestroutes__queue_log_deleted_by ON audit.pestroutes__queue_log USING btree (deleted_by);

CREATE INDEX idx_pestroutes__queue_log_pestroutes_sync_status_pending_only ON audit.pestroutes__queue_log USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_pestroutes__queue_log_table_id ON audit.pestroutes__queue_log USING btree (table_id);

CREATE INDEX idx_pestroutes__queue_log_updated_at ON audit.pestroutes__queue_log USING btree (updated_at);

CREATE INDEX idx_pestroutes__queue_log_updated_by ON audit.pestroutes__queue_log USING btree (updated_by);

CREATE INDEX idx_pestroutes__queue_pestroutes_sync_status_pending_only ON audit.pestroutes__queue USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_pestroutes__queue_table_id ON audit.pestroutes__queue USING btree (table_id);

CREATE INDEX idx_pestroutes__queue_updated_at ON audit.pestroutes__queue USING btree (updated_at);

CREATE INDEX idx_pestroutes__queue_updated_by ON audit.pestroutes__queue USING btree (updated_by);

CREATE INDEX idx_spt__polygon_stats_created_at ON audit.spt__polygon_stats USING btree (created_at);

CREATE INDEX idx_spt__polygon_stats_created_by ON audit.spt__polygon_stats USING btree (created_by);

CREATE INDEX idx_spt__polygon_stats_deleted_by ON audit.spt__polygon_stats USING btree (deleted_by);

CREATE INDEX idx_spt__polygon_stats_pestroutes_sync_status_pending_only ON audit.spt__polygon_stats USING btree (pestroutes_sync_status) WHERE (pestroutes_sync_status = 'PENDING'::text);

CREATE INDEX idx_spt__polygon_stats_table_id ON audit.spt__polygon_stats USING btree (table_id);

CREATE INDEX idx_spt__polygon_stats_updated_at ON audit.spt__polygon_stats USING btree (updated_at);

CREATE INDEX idx_spt__polygon_stats_updated_by ON audit.spt__polygon_stats USING btree (updated_by);

CREATE INDEX idx_api_account_roles_api_account_id ON auth.api_account_roles USING btree (api_account_id);

CREATE INDEX idx_api_account_roles_role_id ON auth.api_account_roles USING btree (role_id);

CREATE INDEX idx_auth__actions_deleted_at ON auth.actions USING btree (deleted_at);

CREATE INDEX idx_auth__api_account_roles_deleted_at ON auth.api_account_roles USING btree (deleted_at);

CREATE INDEX idx_auth__fields_deleted_at ON auth.fields USING btree (deleted_at);

CREATE INDEX idx_auth__idp_roles_deleted_at ON auth.idp_roles USING btree (deleted_at);

CREATE INDEX idx_auth__permissions_deleted_at ON auth.permissions USING btree (deleted_at);

CREATE INDEX idx_auth__resources_deleted_at ON auth.resources USING btree (deleted_at);

CREATE INDEX idx_auth__role_permissions_deleted_at ON auth.role_permissions USING btree (deleted_at);

CREATE INDEX idx_auth__roles_deleted_at ON auth.roles USING btree (deleted_at);

CREATE INDEX idx_auth__services_deleted_at ON auth.services USING btree (deleted_at);

CREATE INDEX idx_auth__user_roles_deleted_at ON auth.user_roles USING btree (deleted_at);

CREATE INDEX idx_idp_roles_role_id ON auth.idp_roles USING btree (role_id);

CREATE INDEX idx_permissions_action_id ON auth.permissions USING btree (action_id);

CREATE INDEX idx_permissions_field_id ON auth.permissions USING btree (field_id);

CREATE INDEX idx_permissions_resource_id ON auth.permissions USING btree (resource_id);

CREATE INDEX idx_permissions_service_id ON auth.permissions USING btree (service_id);

CREATE INDEX idx_role_permissions_permission_id ON auth.role_permissions USING btree (permission_id);

CREATE INDEX idx_role_permissions_role_id ON auth.role_permissions USING btree (role_id);

CREATE INDEX idx_user_roles_role_id ON auth.user_roles USING btree (role_id);

CREATE INDEX idx_user_roles_user_id ON auth.user_roles USING btree (user_id);

CREATE UNIQUE INDEX permissions_service_id_resource_id_field_id_action_id_idx ON auth.permissions USING btree (service_id, resource_id, field_id, action_id);

CREATE UNIQUE INDEX role_permissions_role_id_permission_id_unique ON auth.role_permissions USING btree (role_id, permission_id);

CREATE UNIQUE INDEX uk_api_account_roles_api_account_id_role_id ON auth.api_account_roles USING btree (api_account_id, role_id);

CREATE UNIQUE INDEX user_roles_user_id_role_id_idx ON auth.user_roles USING btree (user_id, role_id);

CREATE UNIQUE INDEX billing_payment_methods_data_link_alias_unique ON billing.payment_methods USING btree (pestroutes_data_link_alias) WHERE (deleted_at IS NULL);

CREATE UNIQUE INDEX billing_payments_data_link_alias_unique ON billing.payments USING btree (pestroutes_data_link_alias) WHERE (deleted_at IS NULL);

CREATE INDEX decline_reasons_created_at_idx ON billing.decline_reasons USING btree (created_at);

CREATE INDEX decline_reasons_created_by_idx ON billing.decline_reasons USING btree (created_by);

CREATE INDEX decline_reasons_deleted_at_idx ON billing.decline_reasons USING btree (deleted_at);

CREATE INDEX decline_reasons_deleted_by_idx ON billing.decline_reasons USING btree (deleted_by);

CREATE INDEX decline_reasons_updated_at_idx ON billing.decline_reasons USING btree (updated_at);

CREATE INDEX decline_reasons_updated_by_idx ON billing.decline_reasons USING btree (updated_by);

CREATE INDEX default_autopay_payment_methods_created_at_idx ON billing.default_autopay_payment_methods USING btree (created_at);

CREATE INDEX default_autopay_payment_methods_created_by_idx ON billing.default_autopay_payment_methods USING btree (created_by);

CREATE INDEX default_autopay_payment_methods_deleted_at_idx ON billing.default_autopay_payment_methods USING btree (deleted_at);

CREATE INDEX default_autopay_payment_methods_deleted_by_idx ON billing.default_autopay_payment_methods USING btree (deleted_by);

CREATE INDEX default_autopay_payment_methods_updated_at_idx ON billing.default_autopay_payment_methods USING btree (updated_at);

CREATE INDEX default_autopay_payment_methods_updated_by_idx ON billing.default_autopay_payment_methods USING btree (updated_by);

CREATE INDEX failed_jobs_created_at_idx ON billing.failed_jobs USING btree (created_at);

CREATE INDEX failed_jobs_created_by_idx ON billing.failed_jobs USING btree (created_by);

CREATE INDEX failed_jobs_deleted_at_idx ON billing.failed_jobs USING btree (deleted_at);

CREATE INDEX failed_jobs_deleted_by_idx ON billing.failed_jobs USING btree (deleted_by);

CREATE INDEX failed_jobs_updated_at_idx ON billing.failed_jobs USING btree (updated_at);

CREATE INDEX failed_jobs_updated_by_idx ON billing.failed_jobs USING btree (updated_by);

CREATE INDEX failed_refund_payments_created_at_idx ON billing.failed_refund_payments USING btree (created_at);

CREATE INDEX failed_refund_payments_created_by_idx ON billing.failed_refund_payments USING btree (created_by);

CREATE INDEX failed_refund_payments_deleted_at_idx ON billing.failed_refund_payments USING btree (deleted_at);

CREATE INDEX failed_refund_payments_deleted_by_idx ON billing.failed_refund_payments USING btree (deleted_by);

CREATE INDEX failed_refund_payments_updated_at_idx ON billing.failed_refund_payments USING btree (updated_at);

CREATE INDEX failed_refund_payments_updated_by_idx ON billing.failed_refund_payments USING btree (updated_by);

CREATE INDEX idx_billing__account_updater_attempts_deleted_at ON billing.account_updater_attempts USING btree (deleted_at);

CREATE INDEX idx_billing__account_updater_attempts_methods_deleted_at ON billing.account_updater_attempts_methods USING btree (deleted_at);

CREATE INDEX idx_billing__decline_reasons_deleted_at ON billing.decline_reasons USING btree (deleted_at);

CREATE INDEX idx_billing__invoice_items_deleted_at ON billing.invoice_items USING btree (deleted_at);

CREATE INDEX idx_billing__invoices_balance ON billing.invoices USING btree (balance) WHERE (deleted_at IS NULL);

CREATE INDEX idx_billing__invoices_created_at ON billing.invoices USING btree (created_at) WHERE (deleted_at IS NULL);

CREATE INDEX idx_billing__invoices_deleted_at ON billing.invoices USING btree (deleted_at);

CREATE INDEX idx_billing__ledger_deleted_at ON billing.ledger USING btree (deleted_at);

CREATE INDEX idx_billing__ledger_transactions_deleted_at ON billing.ledger_transactions USING btree (deleted_at);

CREATE INDEX idx_billing__payment_gateways_deleted_at ON billing.payment_gateways USING btree (deleted_at);

CREATE INDEX idx_billing__payment_invoice_allocations_deleted_at ON billing.payment_invoice_allocations USING btree (deleted_at);

CREATE INDEX idx_billing__payment_methods_deleted_at ON billing.payment_methods USING btree (deleted_at);

CREATE INDEX idx_billing__payment_statuses_deleted_at ON billing.payment_statuses USING btree (deleted_at);

CREATE INDEX idx_billing__payment_types_deleted_at ON billing.payment_types USING btree (deleted_at);

CREATE INDEX idx_billing__payments_deleted_at ON billing.payments USING btree (deleted_at);

CREATE INDEX idx_billing__payments_original_payment_id ON billing.payments USING btree (original_payment_id);

CREATE INDEX idx_billing__transaction_types_deleted_at ON billing.transaction_types USING btree (deleted_at);

CREATE INDEX idx_billing__transactions_deleted_at ON billing.transactions USING btree (deleted_at);

CREATE INDEX idx_invoice_items_external_ref_id ON billing.invoice_items USING btree (external_ref_id);

CREATE INDEX idx_invoice_items_invoice_id ON billing.invoice_items USING btree (invoice_id);

CREATE INDEX idx_invoice_items_pestroutes_invoice_id ON billing.invoice_items USING btree (pestroutes_invoice_id);

CREATE INDEX idx_invoice_items_pestroutes_service_type_id ON billing.invoice_items USING btree (pestroutes_service_type_id);

CREATE INDEX idx_invoice_items_service_type_id ON billing.invoice_items USING btree (service_type_id);

CREATE INDEX idx_invoices_account_id ON billing.invoices USING btree (account_id);

CREATE INDEX idx_invoices_invoiced_at ON billing.invoices USING btree (invoiced_at);

CREATE INDEX idx_invoices_pestroutes_created_by ON billing.invoices USING btree (pestroutes_created_by);

CREATE INDEX idx_invoices_pestroutes_customer_id ON billing.invoices USING btree (pestroutes_customer_id);

CREATE INDEX idx_invoices_pestroutes_service_type_id ON billing.invoices USING btree (pestroutes_service_type_id);

CREATE INDEX idx_invoices_pestroutes_subscription_id ON billing.invoices USING btree (pestroutes_subscription_id);

CREATE INDEX idx_invoices_service_type_id ON billing.invoices USING btree (service_type_id);

CREATE INDEX idx_ledger_autopay_payment_method_id ON billing.ledger USING btree (autopay_payment_method_id) WHERE (deleted_at IS NULL);

CREATE INDEX idx_payment_invoice_allocations_pestroutes_invoice_id ON billing.payment_invoice_allocations USING btree (pestroutes_invoice_id);

CREATE INDEX idx_payment_methods_account_id ON billing.payment_methods USING btree (account_id) WHERE (deleted_at IS NULL);

CREATE INDEX idx_payment_methods_external_ref_id ON billing.payment_methods USING btree (external_ref_id);

CREATE INDEX idx_payment_methods_payment_gateway_id ON billing.payment_methods USING btree (payment_gateway_id);

CREATE INDEX idx_payment_methods_payment_type_id ON billing.payment_methods USING btree (payment_type_id);

CREATE INDEX idx_payment_methods_pestroutes_created_by ON billing.payment_methods USING btree (pestroutes_created_by);

CREATE INDEX idx_payment_methods_pestroutes_customer_id ON billing.payment_methods USING btree (pestroutes_customer_id);

CREATE INDEX idx_payments_account_id ON billing.payments USING btree (account_id);

CREATE INDEX idx_payments_created_at ON billing.payments USING btree (created_at);

CREATE INDEX idx_payments_external_ref_id ON billing.payments USING btree (external_ref_id);

CREATE INDEX idx_payments_payment_gateway_id ON billing.payments USING btree (payment_gateway_id);

CREATE INDEX idx_payments_payment_method_deleted_processed ON billing.payments USING btree (payment_method_id, processed_at DESC) WHERE (deleted_at IS NULL);

CREATE INDEX idx_payments_payment_status_id ON billing.payments USING btree (payment_status_id);

CREATE INDEX idx_payments_payment_type_id ON billing.payments USING btree (payment_type_id);

CREATE INDEX idx_payments_pestroutes_created_by ON billing.payments USING btree (pestroutes_created_by);

CREATE INDEX idx_payments_pestroutes_customer_id ON billing.payments USING btree (pestroutes_customer_id);

CREATE INDEX idx_payments_pestroutes_original_payment_id ON billing.payments USING btree (pestroutes_original_payment_id);

CREATE INDEX idx_payments_processed_at ON billing.payments USING btree (processed_at DESC NULLS LAST);

CREATE INDEX idx_suspend_reasons_created_at ON billing.suspend_reasons USING btree (created_at);

CREATE INDEX idx_suspend_reasons_created_by ON billing.suspend_reasons USING btree (created_by);

CREATE INDEX idx_suspend_reasons_deleted_at ON billing.suspend_reasons USING btree (deleted_at);

CREATE INDEX idx_suspend_reasons_deleted_by ON billing.suspend_reasons USING btree (deleted_by);

CREATE INDEX idx_suspend_reasons_updated_at ON billing.suspend_reasons USING btree (updated_at);

CREATE INDEX idx_suspend_reasons_updated_by ON billing.suspend_reasons USING btree (updated_by);

CREATE INDEX idx_transactions_payment_type_id ON billing.transactions USING btree (transaction_type_id);

CREATE UNIQUE INDEX payment_invoice_applications_payment_id_invoice_id_key ON billing.payment_invoice_allocations USING btree (payment_id, invoice_id);

CREATE INDEX payment_update_pestroutes_created_by_crm_log_created_at_idx ON billing.payment_update_pestroutes_created_by_crm_log USING btree (created_at);

CREATE INDEX payment_update_pestroutes_created_by_crm_log_created_by_idx ON billing.payment_update_pestroutes_created_by_crm_log USING btree (created_by);

CREATE INDEX payment_update_pestroutes_created_by_crm_log_deleted_at_idx ON billing.payment_update_pestroutes_created_by_crm_log USING btree (deleted_at);

CREATE INDEX payment_update_pestroutes_created_by_crm_log_deleted_by_idx ON billing.payment_update_pestroutes_created_by_crm_log USING btree (deleted_by);

CREATE INDEX payment_update_pestroutes_created_by_crm_log_updated_at_idx ON billing.payment_update_pestroutes_created_by_crm_log USING btree (updated_at);

CREATE INDEX payment_update_pestroutes_created_by_crm_log_updated_by_idx ON billing.payment_update_pestroutes_created_by_crm_log USING btree (updated_by);

CREATE INDEX scheduled_payment_statuses_created_at_idx ON billing.scheduled_payment_statuses USING btree (created_at);

CREATE INDEX scheduled_payment_statuses_created_by_idx ON billing.scheduled_payment_statuses USING btree (created_by);

CREATE INDEX scheduled_payment_statuses_deleted_at_idx ON billing.scheduled_payment_statuses USING btree (deleted_at);

CREATE INDEX scheduled_payment_statuses_deleted_by_idx ON billing.scheduled_payment_statuses USING btree (deleted_by);

CREATE INDEX scheduled_payment_statuses_updated_at_idx ON billing.scheduled_payment_statuses USING btree (updated_at);

CREATE INDEX scheduled_payment_statuses_updated_by_idx ON billing.scheduled_payment_statuses USING btree (updated_by);

CREATE INDEX scheduled_payment_triggers_created_at_idx ON billing.scheduled_payment_triggers USING btree (created_at);

CREATE INDEX scheduled_payment_triggers_created_by_idx ON billing.scheduled_payment_triggers USING btree (created_by);

CREATE INDEX scheduled_payment_triggers_deleted_at_idx ON billing.scheduled_payment_triggers USING btree (deleted_at);

CREATE INDEX scheduled_payment_triggers_deleted_by_idx ON billing.scheduled_payment_triggers USING btree (deleted_by);

CREATE INDEX scheduled_payment_triggers_updated_at_idx ON billing.scheduled_payment_triggers USING btree (updated_at);

CREATE INDEX scheduled_payment_triggers_updated_by_idx ON billing.scheduled_payment_triggers USING btree (updated_by);

CREATE INDEX scheduled_payments_created_at_idx ON billing.scheduled_payments USING btree (created_at);

CREATE INDEX scheduled_payments_created_by_idx ON billing.scheduled_payments USING btree (created_by);

CREATE INDEX scheduled_payments_deleted_at_idx ON billing.scheduled_payments USING btree (deleted_at);

CREATE INDEX scheduled_payments_deleted_by_idx ON billing.scheduled_payments USING btree (deleted_by);

CREATE INDEX scheduled_payments_updated_at_idx ON billing.scheduled_payments USING btree (updated_at);

CREATE INDEX scheduled_payments_updated_by_idx ON billing.scheduled_payments USING btree (updated_by);

CREATE INDEX subscription_autopay_payment_methods_created_at_idx ON billing.subscription_autopay_payment_methods USING btree (created_at);

CREATE INDEX subscription_autopay_payment_methods_created_by_idx ON billing.subscription_autopay_payment_methods USING btree (created_by);

CREATE INDEX subscription_autopay_payment_methods_deleted_at_idx ON billing.subscription_autopay_payment_methods USING btree (deleted_at);

CREATE INDEX subscription_autopay_payment_methods_deleted_by_idx ON billing.subscription_autopay_payment_methods USING btree (deleted_by);

CREATE INDEX subscription_autopay_payment_methods_updated_at_idx ON billing.subscription_autopay_payment_methods USING btree (updated_at);

CREATE INDEX subscription_autopay_payment_methods_updated_by_idx ON billing.subscription_autopay_payment_methods USING btree (updated_by);

CREATE INDEX suspend_reasons_created_at_idx ON billing.suspend_reasons USING btree (created_at);

CREATE INDEX suspend_reasons_created_by_idx ON billing.suspend_reasons USING btree (created_by);

CREATE INDEX suspend_reasons_deleted_at_idx ON billing.suspend_reasons USING btree (deleted_at);

CREATE INDEX suspend_reasons_deleted_by_idx ON billing.suspend_reasons USING btree (deleted_by);

CREATE INDEX suspend_reasons_updated_at_idx ON billing.suspend_reasons USING btree (updated_at);

CREATE INDEX suspend_reasons_updated_by_idx ON billing.suspend_reasons USING btree (updated_by);

CREATE UNIQUE INDEX uk_default_autopay_payment_methods_account_id ON billing.default_autopay_payment_methods USING btree (account_id);

CREATE UNIQUE INDEX uk_subscription_autopay_payment_methods_subscription_id ON billing.subscription_autopay_payment_methods USING btree (subscription_id);

CREATE UNIQUE INDEX crm_generic_flags_data_link_alias_unique ON crm.generic_flags USING btree (pestroutes_data_link_alias);

CREATE UNIQUE INDEX addresses_pestroutes_customer_id_pestroutes_address_type_idx ON customer.addresses USING btree (pestroutes_customer_id, pestroutes_address_type);

CREATE UNIQUE INDEX contacts_pestroutes_customer_id_pestroutes_contact_type_idx ON customer.contacts USING btree (pestroutes_customer_id, pestroutes_contact_type);

CREATE INDEX contracts_created_at_idx ON customer.contracts USING btree (created_at);

CREATE INDEX contracts_created_by_idx ON customer.contracts USING btree (created_by);

CREATE INDEX contracts_deleted_at_idx ON customer.contracts USING btree (deleted_at);

CREATE INDEX contracts_deleted_by_idx ON customer.contracts USING btree (deleted_by);

CREATE INDEX contracts_updated_at_idx ON customer.contracts USING btree (updated_at);

CREATE INDEX contracts_updated_by_idx ON customer.contracts USING btree (updated_by);

CREATE UNIQUE INDEX customer_accounts_data_link_alias_unique ON customer.accounts USING btree (pestroutes_data_link_alias) WHERE (deleted_at IS NULL);

CREATE INDEX documents_created_at_idx ON customer.documents USING btree (created_at);

CREATE INDEX documents_created_by_idx ON customer.documents USING btree (created_by);

CREATE INDEX documents_deleted_at_idx ON customer.documents USING btree (deleted_at);

CREATE INDEX documents_deleted_by_idx ON customer.documents USING btree (deleted_by);

CREATE INDEX documents_updated_at_idx ON customer.documents USING btree (updated_at);

CREATE INDEX documents_updated_by_idx ON customer.documents USING btree (updated_by);

CREATE INDEX forms_created_at_idx ON customer.forms USING btree (created_at);

CREATE INDEX forms_created_by_idx ON customer.forms USING btree (created_by);

CREATE INDEX forms_deleted_at_idx ON customer.forms USING btree (deleted_at);

CREATE INDEX forms_deleted_by_idx ON customer.forms USING btree (deleted_by);

CREATE INDEX forms_updated_at_idx ON customer.forms USING btree (updated_at);

CREATE INDEX forms_updated_by_idx ON customer.forms USING btree (updated_by);

CREATE INDEX idx_accounts_area_id ON customer.accounts USING btree (area_id);

CREATE INDEX idx_accounts_billing_address_id ON customer.accounts USING btree (billing_address_id);

CREATE INDEX idx_accounts_billing_contact_id ON customer.accounts USING btree (billing_contact_id);

CREATE INDEX idx_accounts_contact_id ON customer.accounts USING btree (contact_id);

CREATE INDEX idx_accounts_dealer_id ON customer.accounts USING btree (dealer_id);

CREATE INDEX idx_accounts_external_ref_id ON customer.accounts USING btree (external_ref_id);

CREATE INDEX idx_accounts_pestroutes_created_by ON customer.accounts USING btree (pestroutes_created_by);

CREATE INDEX idx_accounts_service_address_id ON customer.accounts USING btree (service_address_id);

CREATE INDEX idx_addresses_pestroutes_customer_id ON customer.addresses USING btree (pestroutes_customer_id);

CREATE INDEX idx_cancellation_reasons_external_ref_id ON customer.cancellation_reasons USING btree (external_ref_id);

CREATE INDEX idx_contacts_first_name ON customer.contacts USING btree (first_name) WHERE (deleted_at IS NULL);

CREATE INDEX idx_contacts_pestroutes_customer_id ON customer.contacts USING btree (pestroutes_customer_id);

CREATE INDEX idx_customer__accounts_deleted_at ON customer.accounts USING btree (deleted_at);

CREATE INDEX idx_customer__addresses_deleted_at ON customer.addresses USING btree (deleted_at);

CREATE INDEX idx_customer__cancellation_reasons_deleted_at ON customer.cancellation_reasons USING btree (deleted_at);

CREATE INDEX idx_customer__contacts_deleted_at ON customer.contacts USING btree (deleted_at);

CREATE INDEX idx_customer__note_types_deleted_at ON customer.note_types USING btree (deleted_at);

CREATE INDEX idx_customer__notes_deleted_at ON customer.notes USING btree (deleted_at);

CREATE INDEX idx_customer__subscriptions_deleted_at ON customer.subscriptions USING btree (deleted_at);

CREATE INDEX idx_customer_created_at ON customer.accounts USING btree (created_at);

CREATE INDEX idx_note_types_external_ref_id ON customer.note_types USING btree (external_ref_id);

CREATE INDEX idx_notes_account_id ON customer.notes USING btree (account_id);

CREATE INDEX idx_notes_cancellation_reason_id ON customer.notes USING btree (cancellation_reason_id);

CREATE INDEX idx_notes_external_ref_id ON customer.notes USING btree (external_ref_id);

CREATE INDEX idx_notes_note_type_id ON customer.notes USING btree (note_type_id);

CREATE INDEX idx_notes_pestroutes_created_by ON customer.notes USING btree (pestroutes_created_by);

CREATE INDEX idx_notes_pestroutes_customer_id ON customer.notes USING btree (pestroutes_customer_id);

CREATE INDEX idx_subscriptions_external_ref_id ON customer.subscriptions USING btree (external_ref_id);

CREATE INDEX idx_subscriptions_pestroutes_created_by ON customer.subscriptions USING btree (pestroutes_created_by);

CREATE INDEX idx_subscriptions_pestroutes_customer_id ON customer.subscriptions USING btree (pestroutes_customer_id);

CREATE INDEX idx_subscriptions_pestroutes_service_type_id ON customer.subscriptions USING btree (pestroutes_service_type_id);

CREATE INDEX idx_subscriptions_pestroutes_sold_by ON customer.subscriptions USING btree (pestroutes_sold_by);

CREATE INDEX test_tbl_created_at_idx ON dbe_test.test_tbl USING btree (created_at);

CREATE INDEX test_tbl_created_by_idx ON dbe_test.test_tbl USING btree (created_by);

CREATE INDEX test_tbl_deleted_at_idx ON dbe_test.test_tbl USING btree (deleted_at);

CREATE INDEX test_tbl_deleted_by_idx ON dbe_test.test_tbl USING btree (deleted_by);

CREATE INDEX test_tbl_updated_at_idx ON dbe_test.test_tbl USING btree (updated_at);

CREATE INDEX test_tbl_updated_by_idx ON dbe_test.test_tbl USING btree (updated_by);

CREATE INDEX aro_users_created_at_idx ON field_operations.aro_users USING btree (created_at);

CREATE INDEX aro_users_created_by_idx ON field_operations.aro_users USING btree (created_by);

CREATE INDEX aro_users_deleted_at_idx ON field_operations.aro_users USING btree (deleted_at);

CREATE INDEX aro_users_deleted_by_idx ON field_operations.aro_users USING btree (deleted_by);

CREATE INDEX aro_users_updated_at_idx ON field_operations.aro_users USING btree (updated_at);

CREATE INDEX aro_users_updated_by_idx ON field_operations.aro_users USING btree (updated_by);

CREATE INDEX customer_property_details_created_at_idx ON field_operations.customer_property_details USING btree (created_at);

CREATE INDEX customer_property_details_created_by_idx ON field_operations.customer_property_details USING btree (created_by);

CREATE INDEX customer_property_details_deleted_at_idx ON field_operations.customer_property_details USING btree (deleted_at);

CREATE INDEX customer_property_details_deleted_by_idx ON field_operations.customer_property_details USING btree (deleted_by);

CREATE INDEX customer_property_details_updated_at_idx ON field_operations.customer_property_details USING btree (updated_at);

CREATE INDEX customer_property_details_updated_by_idx ON field_operations.customer_property_details USING btree (updated_by);

CREATE INDEX date_idx ON field_operations.optimization_states USING btree (as_of_date);

CREATE INDEX idx_appointment_statuses_external_ref_id ON field_operations.appointment_statuses USING btree (external_ref_id);

CREATE INDEX idx_appointments_appointment_type_id ON field_operations.appointments USING btree (appointment_type_id);

CREATE INDEX idx_appointments_external_ref_id ON field_operations.appointments USING btree (external_ref_id);

CREATE INDEX idx_appointments_pestroutes_cancelled_by ON field_operations.appointments USING btree (pestroutes_cancelled_by);

CREATE INDEX idx_appointments_pestroutes_completed_by ON field_operations.appointments USING btree (pestroutes_completed_by);

CREATE INDEX idx_appointments_pestroutes_created_by ON field_operations.appointments USING btree (pestroutes_created_by);

CREATE INDEX idx_appointments_pestroutes_customer_id ON field_operations.appointments USING btree (pestroutes_customer_id);

CREATE INDEX idx_appointments_pestroutes_invoice_id ON field_operations.appointments USING btree (pestroutes_invoice_id);

CREATE INDEX idx_appointments_pestroutes_route_id ON field_operations.appointments USING btree (pestroutes_route_id);

CREATE INDEX idx_appointments_pestroutes_service_type_id ON field_operations.appointments USING btree (pestroutes_service_type_id);

CREATE INDEX idx_appointments_pestroutes_serviced_by ON field_operations.appointments USING btree (pestroutes_serviced_by);

CREATE INDEX idx_appointments_pestroutes_subscription_id ON field_operations.appointments USING btree (pestroutes_subscription_id);

CREATE INDEX idx_appointments_status_id ON field_operations.appointments USING btree (status_id);

CREATE INDEX idx_appointments_subscription_id ON field_operations.appointments USING btree (subscription_id);

CREATE INDEX idx_areas_external_ref_id ON field_operations.areas USING btree (external_ref_id);

CREATE INDEX idx_customer_property_details_customer_id ON field_operations.customer_property_details USING btree (customer_id);

CREATE INDEX idx_field_operations__appointment_statuses_deleted_at ON field_operations.appointment_statuses USING btree (deleted_at);

CREATE INDEX idx_field_operations__appointment_types_deleted_at ON field_operations.appointment_types USING btree (deleted_at);

CREATE INDEX idx_field_operations__appointments_deleted_at ON field_operations.appointments USING btree (deleted_at);

CREATE INDEX idx_field_operations__areas_deleted_at ON field_operations.areas USING btree (deleted_at);

CREATE INDEX idx_field_operations__aro_failed_jobs_deleted_at ON field_operations.aro_failed_jobs USING btree (deleted_at);

CREATE INDEX idx_field_operations__failure_notification_recipients_deleted_a ON field_operations.failure_notification_recipients USING btree (deleted_at);

CREATE INDEX idx_field_operations__markets_deleted_at ON field_operations.markets USING btree (deleted_at);

CREATE INDEX idx_field_operations__notification_recipient_type_deleted_at ON field_operations.notification_recipient_type USING btree (deleted_at);

CREATE INDEX idx_field_operations__notification_recipients_deleted_at ON field_operations.notification_recipients USING btree (deleted_at);

CREATE INDEX idx_field_operations__notification_types_deleted_at ON field_operations.notification_types USING btree (deleted_at);

CREATE INDEX idx_field_operations__office_days_participants_deleted_at ON field_operations.office_days_participants USING btree (deleted_at);

CREATE INDEX idx_field_operations__office_days_schedule_deleted_at ON field_operations.office_days_schedule USING btree (deleted_at);

CREATE INDEX idx_field_operations__office_days_schedule_overrides_deleted_at ON field_operations.office_days_schedule_overrides USING btree (deleted_at);

CREATE INDEX idx_field_operations__optimization_states_deleted_at ON field_operations.optimization_states USING btree (deleted_at);

CREATE INDEX idx_field_operations__regions_deleted_at ON field_operations.regions USING btree (deleted_at);

CREATE INDEX idx_field_operations__route_actual_stats_deleted_at ON field_operations.route_actual_stats USING btree (deleted_at);

CREATE INDEX idx_field_operations__route_geometries_deleted_at ON field_operations.route_geometries USING btree (deleted_at);

CREATE INDEX idx_field_operations__route_groups_deleted_at ON field_operations.route_groups USING btree (deleted_at);

CREATE INDEX idx_field_operations__route_templates_deleted_at ON field_operations.route_templates USING btree (deleted_at);

CREATE INDEX idx_field_operations__routes_deleted_at ON field_operations.routes USING btree (deleted_at);

CREATE INDEX idx_field_operations__service_actual_stats_deleted_at ON field_operations.service_actual_stats USING btree (deleted_at);

CREATE INDEX idx_field_operations__service_types_deleted_at ON field_operations.service_types USING btree (deleted_at);

CREATE INDEX idx_field_operations__user_areas_deleted_at ON field_operations.user_areas USING btree (deleted_at);

CREATE INDEX idx_monthly_financial_reports_ledger_revenue ON field_operations.monthly_financial_reports USING btree (year, month, ledger_account_type, ledger_account_id, revenue_category_id);

CREATE INDEX idx_monthly_financial_reports_ledger_spend ON field_operations.monthly_financial_reports USING btree (year, month, ledger_account_type, ledger_account_id, spend_category_id);

CREATE INDEX idx_optimization_states_created_at ON field_operations.optimization_states USING btree (created_at);

CREATE INDEX idx_route_details_optimization_state_id ON field_operations.route_details USING btree (optimization_state_id);

CREATE INDEX idx_routes_area_id ON field_operations.routes USING btree (area_id);

CREATE INDEX idx_routes_external_ref_id ON field_operations.routes USING btree (external_ref_id);

CREATE INDEX idx_routes_pestroutes_created_by ON field_operations.routes USING btree (pestroutes_created_by);

CREATE INDEX idx_routes_route_group_id ON field_operations.routes USING btree (route_group_id);

CREATE INDEX idx_scheduled_route_details_scheduling_state_id ON field_operations.scheduled_route_details USING btree (scheduling_state_id);

CREATE INDEX idx_service_types_external_ref_id ON field_operations.service_types USING btree (external_ref_id);

CREATE INDEX idx_service_types_plan_id ON field_operations.service_types USING btree (plan_id);

CREATE INDEX idx_serviced_route_details_route_id ON field_operations.serviced_route_details USING btree (route_id);

CREATE INDEX idx_serviced_route_details_treatment_state_id ON field_operations.serviced_route_details USING btree (treatment_state_id);

CREATE INDEX idx_treatment_states_as_of_date ON field_operations.treatment_states USING btree (as_of_date);

CREATE INDEX idx_treatment_states_office_id_as_of_date ON field_operations.treatment_states USING btree (office_id, as_of_date);

CREATE INDEX idx_user_areas_area_id ON field_operations.user_areas USING btree (area_id);

CREATE INDEX idx_user_areas_user_id ON field_operations.user_areas USING btree (user_id);

CREATE INDEX monthly_financial_reports_created_at_idx ON field_operations.monthly_financial_reports USING btree (created_at);

CREATE INDEX monthly_financial_reports_created_by_idx ON field_operations.monthly_financial_reports USING btree (created_by);

CREATE INDEX monthly_financial_reports_deleted_at_idx ON field_operations.monthly_financial_reports USING btree (deleted_at);

CREATE INDEX monthly_financial_reports_deleted_by_idx ON field_operations.monthly_financial_reports USING btree (deleted_by);

CREATE INDEX monthly_financial_reports_updated_at_idx ON field_operations.monthly_financial_reports USING btree (updated_at);

CREATE INDEX monthly_financial_reports_updated_by_idx ON field_operations.monthly_financial_reports USING btree (updated_by);

CREATE INDEX notification_recipient_type_created_at_idx ON field_operations.notification_recipient_type USING btree (created_at);

CREATE INDEX notification_recipient_type_created_by_idx ON field_operations.notification_recipient_type USING btree (created_by);

CREATE INDEX notification_recipient_type_deleted_at_idx ON field_operations.notification_recipient_type USING btree (deleted_at);

CREATE INDEX notification_recipient_type_deleted_by_idx ON field_operations.notification_recipient_type USING btree (deleted_by);

CREATE INDEX notification_recipient_type_updated_at_idx ON field_operations.notification_recipient_type USING btree (updated_at);

CREATE INDEX notification_recipient_type_updated_by_idx ON field_operations.notification_recipient_type USING btree (updated_by);

CREATE INDEX notification_recipients_created_at_idx ON field_operations.notification_recipients USING btree (created_at);

CREATE INDEX notification_recipients_created_by_idx ON field_operations.notification_recipients USING btree (created_by);

CREATE INDEX notification_recipients_deleted_at_idx ON field_operations.notification_recipients USING btree (deleted_at);

CREATE INDEX notification_recipients_deleted_by_idx ON field_operations.notification_recipients USING btree (deleted_by);

CREATE INDEX notification_recipients_updated_at_idx ON field_operations.notification_recipients USING btree (updated_at);

CREATE INDEX notification_recipients_updated_by_idx ON field_operations.notification_recipients USING btree (updated_by);

CREATE INDEX notification_types_created_at_idx ON field_operations.notification_types USING btree (created_at);

CREATE INDEX notification_types_created_by_idx ON field_operations.notification_types USING btree (created_by);

CREATE INDEX notification_types_deleted_at_idx ON field_operations.notification_types USING btree (deleted_at);

CREATE INDEX notification_types_deleted_by_idx ON field_operations.notification_types USING btree (deleted_by);

CREATE INDEX notification_types_updated_at_idx ON field_operations.notification_types USING btree (updated_at);

CREATE INDEX notification_types_updated_by_idx ON field_operations.notification_types USING btree (updated_by);

CREATE INDEX office_id_as_of_date_idx ON field_operations.service_actual_stats USING btree (office_id, as_of_date);

CREATE INDEX office_id_idx ON field_operations.optimization_states USING btree ((((office ->> 'office_id'::text))::integer));

CREATE INDEX office_id_start_date ON field_operations.office_days_schedule USING btree (office_id, start_date);

CREATE INDEX route_details_created_at_idx ON field_operations.route_details USING btree (created_at);

CREATE INDEX route_details_created_by_idx ON field_operations.route_details USING btree (created_by);

CREATE INDEX route_details_deleted_at_idx ON field_operations.route_details USING btree (deleted_at);

CREATE INDEX route_details_deleted_by_idx ON field_operations.route_details USING btree (deleted_by);

CREATE INDEX route_details_updated_at_idx ON field_operations.route_details USING btree (updated_at);

CREATE INDEX route_details_updated_by_idx ON field_operations.route_details USING btree (updated_by);

CREATE INDEX route_geometries_created_at_idx ON field_operations.route_geometries USING btree (created_at);

CREATE INDEX route_geometries_created_by_idx ON field_operations.route_geometries USING btree (created_by);

CREATE INDEX route_geometries_deleted_at_idx ON field_operations.route_geometries USING btree (deleted_at);

CREATE INDEX route_geometries_deleted_by_idx ON field_operations.route_geometries USING btree (deleted_by);

CREATE INDEX route_geometries_updated_at_idx ON field_operations.route_geometries USING btree (updated_at);

CREATE INDEX route_geometries_updated_by_idx ON field_operations.route_geometries USING btree (updated_by);

CREATE INDEX route_id_as_of_date_idx ON field_operations.route_actual_stats USING btree (route_id, as_of_date);

CREATE INDEX route_id_idx ON field_operations.route_stats USING btree (route_id);

CREATE INDEX route_templates_created_at_idx ON field_operations.route_templates USING btree (created_at);

CREATE INDEX route_templates_created_by_idx ON field_operations.route_templates USING btree (created_by);

CREATE INDEX route_templates_deleted_at_idx ON field_operations.route_templates USING btree (deleted_at);

CREATE INDEX route_templates_deleted_by_idx ON field_operations.route_templates USING btree (deleted_by);

CREATE INDEX route_templates_updated_at_idx ON field_operations.route_templates USING btree (updated_at);

CREATE INDEX route_templates_updated_by_idx ON field_operations.route_templates USING btree (updated_by);

CREATE INDEX schedule_employee ON field_operations.office_days_participants USING btree (schedule_id, employee_id);

CREATE INDEX scheduled_route_details_created_at_idx ON field_operations.scheduled_route_details USING btree (created_at);

CREATE INDEX scheduled_route_details_created_by_idx ON field_operations.scheduled_route_details USING btree (created_by);

CREATE INDEX scheduled_route_details_deleted_at_idx ON field_operations.scheduled_route_details USING btree (deleted_at);

CREATE INDEX scheduled_route_details_deleted_by_idx ON field_operations.scheduled_route_details USING btree (deleted_by);

CREATE INDEX scheduled_route_details_updated_at_idx ON field_operations.scheduled_route_details USING btree (updated_at);

CREATE INDEX scheduled_route_details_updated_by_idx ON field_operations.scheduled_route_details USING btree (updated_by);

CREATE INDEX scheduling_states_as_of_date_office_id_idx ON field_operations.scheduling_states USING btree (as_of_date, office_id);

CREATE INDEX scheduling_states_created_at_idx ON field_operations.scheduling_states USING btree (created_at);

CREATE INDEX scheduling_states_created_by_idx ON field_operations.scheduling_states USING btree (created_by);

CREATE INDEX scheduling_states_deleted_at_idx ON field_operations.scheduling_states USING btree (deleted_at);

CREATE INDEX scheduling_states_deleted_by_idx ON field_operations.scheduling_states USING btree (deleted_by);

CREATE INDEX scheduling_states_updated_at_idx ON field_operations.scheduling_states USING btree (updated_at);

CREATE INDEX scheduling_states_updated_by_idx ON field_operations.scheduling_states USING btree (updated_by);

CREATE INDEX service_pro_id_field_idx ON field_operations.route_actual_stats USING btree (service_pro_id);

CREATE INDEX service_pro_id_idx ON field_operations.route_stats USING btree ((((service_pro ->> 'service_pro_id'::text))::integer));

CREATE INDEX serviced_route_details_created_at_idx ON field_operations.serviced_route_details USING btree (created_at);

CREATE INDEX serviced_route_details_created_by_idx ON field_operations.serviced_route_details USING btree (created_by);

CREATE INDEX serviced_route_details_deleted_at_idx ON field_operations.serviced_route_details USING btree (deleted_at);

CREATE INDEX serviced_route_details_deleted_by_idx ON field_operations.serviced_route_details USING btree (deleted_by);

CREATE INDEX serviced_route_details_updated_at_idx ON field_operations.serviced_route_details USING btree (updated_at);

CREATE INDEX serviced_route_details_updated_by_idx ON field_operations.serviced_route_details USING btree (updated_by);

CREATE INDEX treatment_states_created_at_idx ON field_operations.treatment_states USING btree (created_at);

CREATE INDEX treatment_states_created_by_idx ON field_operations.treatment_states USING btree (created_by);

CREATE INDEX treatment_states_deleted_at_idx ON field_operations.treatment_states USING btree (deleted_at);

CREATE INDEX treatment_states_deleted_by_idx ON field_operations.treatment_states USING btree (deleted_by);

CREATE INDEX treatment_states_updated_at_idx ON field_operations.treatment_states USING btree (updated_at);

CREATE INDEX treatment_states_updated_by_idx ON field_operations.treatment_states USING btree (updated_by);

CREATE INDEX county_boundary ON licensing.counties USING gist (boundary);

CREATE INDEX municipality_boundary ON licensing.municipalities USING gist (boundary);

CREATE INDEX state_boundary ON licensing.states USING gist (boundary);

CREATE INDEX cache_created_at_idx ON notifications.cache USING btree (created_at);

CREATE INDEX cache_created_by_idx ON notifications.cache USING btree (created_by);

CREATE INDEX cache_deleted_at_idx ON notifications.cache USING btree (deleted_at);

CREATE INDEX cache_deleted_by_idx ON notifications.cache USING btree (deleted_by);

CREATE INDEX cache_updated_at_idx ON notifications.cache USING btree (updated_at);

CREATE INDEX cache_updated_by_idx ON notifications.cache USING btree (updated_by);

CREATE INDEX headshot_paths_created_at_idx ON notifications.headshot_paths USING btree (created_at);

CREATE INDEX headshot_paths_created_by_idx ON notifications.headshot_paths USING btree (created_by);

CREATE INDEX headshot_paths_deleted_at_idx ON notifications.headshot_paths USING btree (deleted_at);

CREATE INDEX headshot_paths_deleted_by_idx ON notifications.headshot_paths USING btree (deleted_by);

CREATE INDEX headshot_paths_updated_at_idx ON notifications.headshot_paths USING btree (updated_at);

CREATE INDEX headshot_paths_updated_by_idx ON notifications.headshot_paths USING btree (updated_by);

CREATE INDEX idx_notification_sent_reference_id ON notifications.notifications_sent USING btree (reference_id);

CREATE INDEX idx_notifications__notifications_sent_deleted_at ON notifications.notifications_sent USING btree (deleted_at);

CREATE INDEX idx_notifications_logs_log_id ON notifications.logs_email USING btree (log_id);

CREATE INDEX idx_notifications_logs_reference_id ON notifications.logs USING btree (reference_id);

CREATE INDEX idx_notifications_logs_type ON notifications.logs USING btree (type);

CREATE INDEX incoming_mms_messages_created_at_idx ON notifications.incoming_mms_messages USING btree (created_at);

CREATE INDEX incoming_mms_messages_created_by_idx ON notifications.incoming_mms_messages USING btree (created_by);

CREATE INDEX incoming_mms_messages_deleted_at_idx ON notifications.incoming_mms_messages USING btree (deleted_at);

CREATE INDEX incoming_mms_messages_deleted_by_idx ON notifications.incoming_mms_messages USING btree (deleted_by);

CREATE INDEX incoming_mms_messages_updated_at_idx ON notifications.incoming_mms_messages USING btree (updated_at);

CREATE INDEX incoming_mms_messages_updated_by_idx ON notifications.incoming_mms_messages USING btree (updated_by);

CREATE INDEX logs_created_at_idx ON notifications.logs USING btree (created_at);

CREATE INDEX logs_created_by_idx ON notifications.logs USING btree (created_by);

CREATE INDEX logs_deleted_at_idx ON notifications.logs USING btree (deleted_at);

CREATE INDEX logs_deleted_by_idx ON notifications.logs USING btree (deleted_by);

CREATE INDEX logs_email_created_at_idx ON notifications.logs_email USING btree (created_at);

CREATE INDEX logs_email_created_by_idx ON notifications.logs_email USING btree (created_by);

CREATE INDEX logs_email_deleted_at_idx ON notifications.logs_email USING btree (deleted_at);

CREATE INDEX logs_email_deleted_by_idx ON notifications.logs_email USING btree (deleted_by);

CREATE INDEX logs_email_updated_at_idx ON notifications.logs_email USING btree (updated_at);

CREATE INDEX logs_email_updated_by_idx ON notifications.logs_email USING btree (updated_by);

CREATE INDEX logs_updated_at_idx ON notifications.logs USING btree (updated_at);

CREATE INDEX logs_updated_by_idx ON notifications.logs USING btree (updated_by);

CREATE INDEX notifications_sent_batches_created_at_idx ON notifications.notifications_sent_batches USING btree (created_at);

CREATE INDEX notifications_sent_batches_created_by_idx ON notifications.notifications_sent_batches USING btree (created_by);

CREATE INDEX notifications_sent_batches_deleted_at_idx ON notifications.notifications_sent_batches USING btree (deleted_at);

CREATE INDEX notifications_sent_batches_deleted_by_idx ON notifications.notifications_sent_batches USING btree (deleted_by);

CREATE INDEX notifications_sent_batches_updated_at_idx ON notifications.notifications_sent_batches USING btree (updated_at);

CREATE INDEX notifications_sent_batches_updated_by_idx ON notifications.notifications_sent_batches USING btree (updated_by);

CREATE INDEX sms_messages_created_at_idx ON notifications.sms_messages USING btree (created_at);

CREATE INDEX sms_messages_created_by_idx ON notifications.sms_messages USING btree (created_by);

CREATE INDEX sms_messages_deleted_at_idx ON notifications.sms_messages USING btree (deleted_at);

CREATE INDEX sms_messages_deleted_by_idx ON notifications.sms_messages USING btree (deleted_by);

CREATE INDEX sms_messages_media_created_at_idx ON notifications.sms_messages_media USING btree (created_at);

CREATE INDEX sms_messages_media_created_by_idx ON notifications.sms_messages_media USING btree (created_by);

CREATE INDEX sms_messages_media_deleted_at_idx ON notifications.sms_messages_media USING btree (deleted_at);

CREATE INDEX sms_messages_media_deleted_by_idx ON notifications.sms_messages_media USING btree (deleted_by);

CREATE INDEX sms_messages_media_updated_at_idx ON notifications.sms_messages_media USING btree (updated_at);

CREATE INDEX sms_messages_media_updated_by_idx ON notifications.sms_messages_media USING btree (updated_by);

CREATE INDEX sms_messages_updated_at_idx ON notifications.sms_messages USING btree (updated_at);

CREATE INDEX sms_messages_updated_by_idx ON notifications.sms_messages USING btree (updated_by);

CREATE UNIQUE INDEX uk_notifications_cache_name ON notifications.cache USING btree (name);

CREATE INDEX idx_organization__api_accounts_deleted_at ON organization.api_accounts USING btree (deleted_at);

CREATE INDEX idx_organization__dealers_deleted_at ON organization.dealers USING btree (deleted_at);

CREATE INDEX idx_organization__user_dealers_deleted_at ON organization.user_dealers USING btree (deleted_at);

CREATE INDEX idx_organization__users_deleted_by ON organization.users USING btree (deleted_at);

CREATE UNIQUE INDEX user_external_ref_id ON organization.users USING btree (external_ref_id) WHERE (deleted_at IS NULL);

CREATE INDEX idx_employees_user_id ON pestroutes.employees USING btree (user_id);

CREATE INDEX idx_product__plans_deleted_at ON product.plans USING btree (deleted_at);

CREATE INDEX idx_product__products_deleted_at ON product.products USING btree (deleted_at);

CREATE UNIQUE INDEX teams_name_dealer_id_idx ON sales.teams USING btree (name, dealer_id);

CREATE INDEX cells_area_resolution ON spt.cells USING btree (area_id, resolution);

CREATE INDEX cells_boundary ON spt.cells USING gist (boundary);

CREATE INDEX idx_cluster_active ON spt.cluster USING btree (active);

CREATE INDEX idx_cluster_active_area ON spt.cluster USING btree (active, area_id);

CREATE INDEX idx_cluster_cluster_id ON spt.cluster USING btree (cluster_id);

CREATE INDEX idx_polygon_rep_user_id ON spt.polygon_rep USING btree (user_id) WHERE ((deleted_at IS NULL) AND (active = true));

CREATE INDEX idx_team_cluster_id ON spt.team_cluster USING btree (cluster_id);

CREATE INDEX idx_team_cluster_team_id ON spt.team_cluster USING btree (team_id);

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON spt.personal_access_tokens USING btree (tokenable_type, tokenable_id);

CREATE INDEX polygon_boundary ON spt.polygon USING gist (boundary) WHERE (deleted_at IS NULL);

CREATE INDEX polygon_stats_created_at_idx ON spt.polygon_stats USING btree (created_at);

CREATE INDEX polygon_stats_created_by_idx ON spt.polygon_stats USING btree (created_by);

CREATE INDEX polygon_stats_deleted_at_idx ON spt.polygon_stats USING btree (deleted_at);

CREATE INDEX polygon_stats_deleted_by_idx ON spt.polygon_stats USING btree (deleted_by);

CREATE INDEX polygon_stats_updated_at_idx ON spt.polygon_stats USING btree (updated_at);

CREATE INDEX polygon_stats_updated_by_idx ON spt.polygon_stats USING btree (updated_by);

CREATE INDEX polygon_team_id ON spt.polygon USING btree (team_id) WHERE (deleted_at IS NULL);

CREATE INDEX team_extended_boundary ON spt.team_extended USING gist (boundary);

CREATE INDEX geog_index ON street_smarts.pins USING gist (public.geography(point)) WHERE (deleted_at IS NULL);

CREATE INDEX idx_pin_issues_created_by ON street_smarts.pin_issues USING btree (created_by);

CREATE INDEX idx_pin_issues_issue ON street_smarts.pin_issues USING btree (issue);

CREATE INDEX idx_pin_issues_pin_id ON street_smarts.pin_issues USING btree (pin_id);

CREATE INDEX knocks_created_at_index ON street_smarts.knocks USING btree (created_at DESC);

CREATE INDEX knocks_outcome_id_index ON street_smarts.knocks USING btree (outcome_id);

CREATE INDEX knocks_pin_id ON street_smarts.knocks USING btree (pin_id);

CREATE INDEX knocks_point ON street_smarts.knocks USING gist (point);

CREATE INDEX knocks_polygon_id ON street_smarts.knocks USING btree (polygon_id);

CREATE INDEX knocks_rep_point ON street_smarts.knocks USING gist (rep_point);

CREATE INDEX knocks_user_id ON street_smarts.knocks USING btree (user_id);

CREATE INDEX pin_geocoding_overrides_created_at_idx ON street_smarts.pin_geocoding_overrides USING btree (created_at);

CREATE INDEX pin_geocoding_overrides_deleted_at_idx ON street_smarts.pin_geocoding_overrides USING btree (deleted_at);

CREATE INDEX pin_geocoding_overrides_updated_at_idx ON street_smarts.pin_geocoding_overrides USING btree (updated_at);

CREATE INDEX pin_is_qualified ON street_smarts.pins_old USING btree (is_qualified) WHERE (deleted_at IS NULL);

CREATE INDEX pin_notes_pin_id ON street_smarts.pin_notes USING btree (pin_id);

CREATE INDEX pin_point ON street_smarts.pins_old USING gist (point) WHERE (deleted_at IS NULL);

CREATE INDEX pin_record_status ON street_smarts.pins_old USING btree (record_status) WHERE (deleted_at IS NULL);

CREATE INDEX pin_zip ON street_smarts.pins_old USING btree (zip) WHERE (deleted_at IS NULL);

CREATE INDEX pins_full_address_index ON street_smarts.pins USING gin (to_tsvector('simple'::regconfig, public.f_concat_ws(' '::text, VARIADIC ARRAY[(address)::text, (city)::text, (state)::text, (zip)::text]))) WHERE (deleted_at IS NULL);

CREATE INDEX pins_is_qualified ON street_smarts.pins USING btree (is_qualified) WHERE (deleted_at IS NULL);

CREATE INDEX pins_point ON street_smarts.pins USING gist (point) WHERE (deleted_at IS NULL);

CREATE INDEX pins_record_status ON street_smarts.pins USING btree (record_status) WHERE (deleted_at IS NULL);

CREATE INDEX pins_zip ON street_smarts.pins USING btree (zip) WHERE (deleted_at IS NULL);

CREATE INDEX s3_loads_s3_key ON street_smarts.s3_loads_log USING btree (s3_key);

CREATE INDEX s3_loads_target_table ON street_smarts.s3_loads_log USING btree (target_table);

CREATE INDEX zips_area_id ON street_smarts.zips USING btree (area_id);

CREATE INDEX zips_boundary ON street_smarts.zips USING gist (boundary);

CREATE INDEX zips_team_id ON street_smarts.zips USING btree (team_id);

CREATE TRIGGER billing__invoices_before_update BEFORE UPDATE ON audit.billing__invoices FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER billing__ledger_before_update BEFORE UPDATE ON audit.billing__ledger FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER billing__payment_methods_before_update BEFORE UPDATE ON audit.billing__payment_methods FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER billing__payments_before_update BEFORE UPDATE ON audit.billing__payments FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER billing__test_payments_before_update BEFORE UPDATE ON audit.billing__test_payments FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER customer__accounts_before_update BEFORE UPDATE ON audit.customer__accounts FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER customer__addresses_before_update BEFORE UPDATE ON audit.customer__addresses FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER customer__contacts_before_update BEFORE UPDATE ON audit.customer__contacts FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER customer__notes_before_update BEFORE UPDATE ON audit.customer__notes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER customer__subscriptions_before_update BEFORE UPDATE ON audit.customer__subscriptions FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER field_operations__appointments_before_update BEFORE UPDATE ON audit.field_operations__appointments FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER field_operations__routes_before_update BEFORE UPDATE ON audit.field_operations__routes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER field_operations__service_types_before_update BEFORE UPDATE ON audit.field_operations__service_types FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER actions_before_update BEFORE UPDATE ON auth.actions FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER api_account_roles_before_update BEFORE UPDATE ON auth.api_account_roles FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER fields_before_update BEFORE UPDATE ON auth.fields FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER idp_roles_before_update BEFORE UPDATE ON auth.idp_roles FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER permissions_before_update BEFORE UPDATE ON auth.permissions FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER resources_before_update BEFORE UPDATE ON auth.resources FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER role_permissions_before_update BEFORE UPDATE ON auth.role_permissions FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER roles_before_update BEFORE UPDATE ON auth.roles FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER services_before_update BEFORE UPDATE ON auth.services FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER user_roles_before_update BEFORE UPDATE ON auth.user_roles FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER decline_reasons_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON billing.decline_reasons FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER decline_reasons_update_updated_at BEFORE UPDATE ON billing.decline_reasons FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER default_autopay_payment_methods_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON billing.default_autopay_payment_methods FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER default_autopay_payment_methods_update_updated_at BEFORE UPDATE ON billing.default_autopay_payment_methods FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER failed_jobs_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON billing.failed_jobs FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER failed_jobs_update_updated_at BEFORE UPDATE ON billing.failed_jobs FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER failed_refund_payments_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON billing.failed_refund_payments FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER failed_refund_payments_update_updated_at BEFORE UPDATE ON billing.failed_refund_payments FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER invoices_after_insert_or_update_or_delete AFTER INSERT OR DELETE OR UPDATE ON billing.invoices FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER invoices_before_update BEFORE UPDATE ON billing.invoices FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER ledger_after_insert_or_update_or_delete AFTER INSERT OR DELETE OR UPDATE ON billing.ledger FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER ledger_before_update BEFORE UPDATE ON billing.ledger FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER payment_gateways_before_update BEFORE UPDATE ON billing.payment_gateways FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER payment_invoice_allocations_before_update BEFORE UPDATE ON billing.payment_invoice_allocations FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER payment_methods_after_insert_or_update_or_delete AFTER INSERT OR DELETE OR UPDATE ON billing.payment_methods FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER payment_methods_before_update BEFORE UPDATE ON billing.payment_methods FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER payment_statuses_before_update BEFORE UPDATE ON billing.payment_statuses FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER payment_types_before_update BEFORE UPDATE ON billing.payment_types FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER payment_update_pestroutes_created_by_crm_log_audit_record_trigg AFTER INSERT OR DELETE OR UPDATE ON billing.payment_update_pestroutes_created_by_crm_log FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER payment_update_pestroutes_created_by_crm_log_update_updated_at BEFORE UPDATE ON billing.payment_update_pestroutes_created_by_crm_log FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER payments_after_insert_or_update_or_delete AFTER INSERT OR DELETE OR UPDATE ON billing.payments FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER payments_before_update BEFORE UPDATE ON billing.payments FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER scheduled_payment_statuses_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON billing.scheduled_payment_statuses FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER scheduled_payment_statuses_update_updated_at BEFORE UPDATE ON billing.scheduled_payment_statuses FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER scheduled_payment_triggers_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON billing.scheduled_payment_triggers FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER scheduled_payment_triggers_update_updated_at BEFORE UPDATE ON billing.scheduled_payment_triggers FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER scheduled_payments_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON billing.scheduled_payments FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER scheduled_payments_update_updated_at BEFORE UPDATE ON billing.scheduled_payments FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER subscription_autopay_payment_methods_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON billing.subscription_autopay_payment_methods FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER subscription_autopay_payment_methods_update_updated_at BEFORE UPDATE ON billing.subscription_autopay_payment_methods FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER suspend_reasons_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON billing.suspend_reasons FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER suspend_reasons_update_updated_at BEFORE UPDATE ON billing.suspend_reasons FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER generic_flags_before_update BEFORE UPDATE ON crm.generic_flags FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER accounts_after_insert_or_update_or_delete AFTER INSERT OR DELETE OR UPDATE ON customer.accounts FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER accounts_before_update BEFORE UPDATE ON customer.accounts FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER addresses_after_insert_or_update_or_delete AFTER INSERT OR DELETE OR UPDATE ON customer.addresses FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER addresses_before_update BEFORE UPDATE ON customer.addresses FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER cancellation_reasons_before_update BEFORE UPDATE ON customer.cancellation_reasons FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER contacts_after_insert_or_update_or_delete AFTER INSERT OR DELETE OR UPDATE ON customer.contacts FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER contacts_before_update BEFORE UPDATE ON customer.contacts FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER contracts_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON customer.contracts FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER contracts_update_updated_at BEFORE UPDATE ON customer.contracts FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER documents_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON customer.documents FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER documents_update_updated_at BEFORE UPDATE ON customer.documents FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER forms_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON customer.forms FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER forms_update_updated_at BEFORE UPDATE ON customer.forms FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER note_types_before_update BEFORE UPDATE ON customer.note_types FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER subscriptions_after_insert_or_update_or_delete AFTER INSERT OR DELETE OR UPDATE ON customer.subscriptions FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER subscriptions_before_update BEFORE UPDATE ON customer.subscriptions FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER test_tbl_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON dbe_test.test_tbl FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER test_tbl_update_updated_at BEFORE UPDATE ON dbe_test.test_tbl FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER appointment_statuses_before_update BEFORE UPDATE ON field_operations.appointment_statuses FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER appointment_types_before_update BEFORE UPDATE ON field_operations.appointment_types FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER appointments_after_insert_or_update_or_delete AFTER INSERT OR DELETE OR UPDATE ON field_operations.appointments FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER appointments_before_update BEFORE UPDATE ON field_operations.appointments FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER areas_before_update BEFORE UPDATE ON field_operations.areas FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER aro_users_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON field_operations.aro_users FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER aro_users_update_updated_at BEFORE UPDATE ON field_operations.aro_users FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER customer_property_details_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON field_operations.customer_property_details FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER customer_property_details_update_updated_at BEFORE UPDATE ON field_operations.customer_property_details FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER markets_before_update BEFORE UPDATE ON field_operations.markets FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER monthly_financial_reports_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON field_operations.monthly_financial_reports FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER monthly_financial_reports_update_updated_at BEFORE UPDATE ON field_operations.monthly_financial_reports FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER notification_recipient_type_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON field_operations.notification_recipient_type FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER notification_recipient_type_update_updated_at BEFORE UPDATE ON field_operations.notification_recipient_type FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER notification_recipients_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON field_operations.notification_recipients FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER notification_recipients_update_updated_at BEFORE UPDATE ON field_operations.notification_recipients FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER notification_types_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON field_operations.notification_types FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER notification_types_update_updated_at BEFORE UPDATE ON field_operations.notification_types FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER regions_before_update BEFORE UPDATE ON field_operations.regions FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER route_details_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON field_operations.route_details FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER route_details_update_updated_at BEFORE UPDATE ON field_operations.route_details FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER route_geometries_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON field_operations.route_geometries FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER route_geometries_update_updated_at BEFORE UPDATE ON field_operations.route_geometries FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER route_groups_before_update BEFORE UPDATE ON field_operations.route_groups FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER route_templates_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON field_operations.route_templates FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER route_templates_update_updated_at BEFORE UPDATE ON field_operations.route_templates FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER routes_after_insert_or_update_or_delete AFTER INSERT OR DELETE OR UPDATE ON field_operations.routes FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER routes_before_update BEFORE UPDATE ON field_operations.routes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER scheduled_route_details_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON field_operations.scheduled_route_details FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER scheduled_route_details_update_updated_at BEFORE UPDATE ON field_operations.scheduled_route_details FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER scheduling_states_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON field_operations.scheduling_states FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER scheduling_states_update_updated_at BEFORE UPDATE ON field_operations.scheduling_states FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER service_types_before_update BEFORE UPDATE ON field_operations.service_types FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER serviced_route_details_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON field_operations.serviced_route_details FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER serviced_route_details_update_updated_at BEFORE UPDATE ON field_operations.serviced_route_details FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER treatment_states_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON field_operations.treatment_states FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER treatment_states_update_updated_at BEFORE UPDATE ON field_operations.treatment_states FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER user_areas_before_update BEFORE UPDATE ON field_operations.user_areas FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER cache_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON notifications.cache FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER cache_update_updated_at BEFORE UPDATE ON notifications.cache FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER headshot_paths_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON notifications.headshot_paths FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER headshot_paths_update_updated_at BEFORE UPDATE ON notifications.headshot_paths FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER incoming_mms_messages_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON notifications.incoming_mms_messages FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER incoming_mms_messages_update_updated_at BEFORE UPDATE ON notifications.incoming_mms_messages FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER logs_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON notifications.logs FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER logs_email_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON notifications.logs_email FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER logs_email_update_updated_at BEFORE UPDATE ON notifications.logs_email FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER logs_update_updated_at BEFORE UPDATE ON notifications.logs FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER notifications_sent_batches_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON notifications.notifications_sent_batches FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER notifications_sent_batches_update_updated_at BEFORE UPDATE ON notifications.notifications_sent_batches FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER notifications_sent_update_updated_at BEFORE UPDATE ON notifications.notifications_sent FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER sms_messages_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON notifications.sms_messages FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER sms_messages_media_audit_record_trigger AFTER INSERT OR DELETE OR UPDATE ON notifications.sms_messages_media FOR EACH ROW EXECUTE FUNCTION public.create_audit_record();

CREATE TRIGGER sms_messages_media_update_updated_at BEFORE UPDATE ON notifications.sms_messages_media FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER sms_messages_update_updated_at BEFORE UPDATE ON notifications.sms_messages FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER api_accounts_before_update BEFORE UPDATE ON organization.api_accounts FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER dealers_before_update BEFORE UPDATE ON organization.dealers FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER user_dealers_before_update BEFORE UPDATE ON organization.user_dealers FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER users_before_update BEFORE UPDATE ON organization.users FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER plans_before_update BEFORE UPDATE ON product.plans FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER team_territories_before_update BEFORE UPDATE ON sales.team_territories FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER teams_before_update BEFORE UPDATE ON sales.teams FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER territories_before_update BEFORE UPDATE ON sales.territories FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER user_teams_before_update BEFORE UPDATE ON sales.user_teams FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER area_extended_update_updated_at BEFORE UPDATE ON spt.area_extended FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER cluster_update_updated_at BEFORE UPDATE ON spt.cluster FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER polygon_cluster_update_updated_at BEFORE UPDATE ON spt.polygon_cluster FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER polygon_rep_update_updated_at BEFORE UPDATE ON spt.polygon_rep FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER polygon_stats_update_updated_at BEFORE UPDATE ON spt.polygon_stats FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER polygon_update_updated_at BEFORE UPDATE ON spt.polygon FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER team_cluster_update_updated_at BEFORE UPDATE ON spt.team_cluster FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER team_extended_update_team_extended BEFORE UPDATE ON spt.team_extended FOR EACH ROW EXECUTE FUNCTION spt.update_team_extended();

CREATE TRIGGER cluster_pins_update_updated_at BEFORE UPDATE ON street_smarts.cluster_pin FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER is_qualified_update_trigger AFTER INSERT OR UPDATE ON street_smarts.pins FOR EACH ROW EXECUTE FUNCTION public.is_qualified_update_trigger_fnc();

CREATE TRIGGER knock_periods_update_updated_at BEFORE UPDATE ON street_smarts.knock_periods FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER knocks_update_updated_at BEFORE UPDATE ON street_smarts.knocks FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER outcomes_update_updated_at BEFORE UPDATE ON street_smarts.outcomes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER pin_geocoding_overrides_update_updated_at BEFORE UPDATE ON street_smarts.pin_geocoding_overrides FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER pin_notes_update_updated_at BEFORE UPDATE ON street_smarts.pin_notes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER pins_update_updated_at BEFORE UPDATE ON street_smarts.pins FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

CREATE TRIGGER zip_update_updated_at BEFORE UPDATE ON street_smarts.zips FOR EACH ROW EXECUTE FUNCTION public.update_updated_at();

ALTER TABLE ONLY auth.idp_roles
    ADD CONSTRAINT idp_roles_role_id_fkey FOREIGN KEY (role_id) REFERENCES auth.roles(id);

ALTER TABLE ONLY auth.permissions
    ADD CONSTRAINT permissions_action_id_fkey FOREIGN KEY (action_id) REFERENCES auth.actions(id) ON DELETE CASCADE;

ALTER TABLE ONLY auth.permissions
    ADD CONSTRAINT permissions_field_id_fkey FOREIGN KEY (field_id) REFERENCES auth.fields(id) ON DELETE CASCADE;

ALTER TABLE ONLY auth.permissions
    ADD CONSTRAINT permissions_resource_id_fkey FOREIGN KEY (resource_id) REFERENCES auth.resources(id) ON DELETE CASCADE;

ALTER TABLE ONLY auth.permissions
    ADD CONSTRAINT permissions_service_id_fkey FOREIGN KEY (service_id) REFERENCES auth.services(id) ON DELETE CASCADE;

ALTER TABLE ONLY auth.role_permissions
    ADD CONSTRAINT role_permissions_permission_id_fkey FOREIGN KEY (permission_id) REFERENCES auth.permissions(id);

ALTER TABLE ONLY auth.role_permissions
    ADD CONSTRAINT role_permissions_role_id_fkey FOREIGN KEY (role_id) REFERENCES auth.roles(id);

ALTER TABLE ONLY auth.user_roles
    ADD CONSTRAINT user_roles_role_id_fkey FOREIGN KEY (role_id) REFERENCES auth.roles(id);

ALTER TABLE ONLY auth.user_roles
    ADD CONSTRAINT user_roles_user_id_fkey FOREIGN KEY (user_id) REFERENCES organization.users(id) ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE ONLY billing.account_updater_attempts_methods
    ADD CONSTRAINT account_updater_attempts_attempt_id_fkey FOREIGN KEY (attempt_id) REFERENCES billing.account_updater_attempts(id);

ALTER TABLE ONLY billing.account_updater_attempts_methods
    ADD CONSTRAINT account_updater_attempts_method_id_fkey FOREIGN KEY (payment_method_id) REFERENCES billing.payment_methods(id);

ALTER TABLE ONLY billing.default_autopay_payment_methods
    ADD CONSTRAINT fk_default_autopay_payment_methods_accounts_id FOREIGN KEY (account_id) REFERENCES customer.accounts(id);

ALTER TABLE ONLY billing.default_autopay_payment_methods
    ADD CONSTRAINT fk_default_autopay_payment_methods_payment_methods_id FOREIGN KEY (payment_method_id) REFERENCES billing.payment_methods(id);

ALTER TABLE ONLY billing.failed_refund_payments
    ADD CONSTRAINT fk_failed_refund_payments_account_id_accounts_id FOREIGN KEY (account_id) REFERENCES customer.accounts(id);

ALTER TABLE ONLY billing.failed_refund_payments
    ADD CONSTRAINT fk_failed_refund_payments_original_payment_id_payments_id FOREIGN KEY (original_payment_id) REFERENCES billing.payments(id);

ALTER TABLE ONLY billing.failed_refund_payments
    ADD CONSTRAINT fk_failed_refund_payments_refund_payment_id_payments_id FOREIGN KEY (refund_payment_id) REFERENCES billing.payments(id);

ALTER TABLE ONLY billing.payments
    ADD CONSTRAINT fk_payments_suspend_reason_id_suspend_reasons_id FOREIGN KEY (suspend_reason_id) REFERENCES billing.suspend_reasons(id);

ALTER TABLE ONLY billing.scheduled_payments
    ADD CONSTRAINT fk_scheduled_payments_account_id_accounts_id FOREIGN KEY (account_id) REFERENCES customer.accounts(id);

ALTER TABLE ONLY billing.scheduled_payments
    ADD CONSTRAINT fk_scheduled_payments_payment_id_payments_id FOREIGN KEY (payment_id) REFERENCES billing.payments(id);

ALTER TABLE ONLY billing.scheduled_payments
    ADD CONSTRAINT fk_scheduled_payments_payment_method_id_payment_methods_id FOREIGN KEY (payment_method_id) REFERENCES billing.payment_methods(id);

ALTER TABLE ONLY billing.scheduled_payments
    ADD CONSTRAINT fk_scheduled_payments_status_id_scheduled_payment_statuses_id FOREIGN KEY (status_id) REFERENCES billing.scheduled_payment_statuses(id);

ALTER TABLE ONLY billing.scheduled_payments
    ADD CONSTRAINT fk_scheduled_payments_trigger_id_scheduled_payment_triggers_id FOREIGN KEY (trigger_id) REFERENCES billing.scheduled_payment_triggers(id);

ALTER TABLE ONLY billing.subscription_autopay_payment_methods
    ADD CONSTRAINT fk_subscription_autopay_payment_methods_payment_methods_id FOREIGN KEY (payment_method_id) REFERENCES billing.payment_methods(id);

ALTER TABLE ONLY billing.subscription_autopay_payment_methods
    ADD CONSTRAINT fk_subscription_autopay_payment_methods_subscriptions_id FOREIGN KEY (subscription_id) REFERENCES customer.subscriptions(id);

ALTER TABLE ONLY billing.invoice_items
    ADD CONSTRAINT invoice_items_invoice_id_fkey FOREIGN KEY (invoice_id) REFERENCES billing.invoices(id);

ALTER TABLE ONLY billing.invoice_items
    ADD CONSTRAINT invoice_items_pestroutes_invoice_id_fk FOREIGN KEY (pestroutes_invoice_id) REFERENCES billing.invoices(external_ref_id);

ALTER TABLE ONLY billing.invoice_items
    ADD CONSTRAINT invoice_items_pestroutes_service_type_id_fk FOREIGN KEY (pestroutes_service_type_id) REFERENCES field_operations.service_types(external_ref_id);

ALTER TABLE ONLY billing.invoice_items
    ADD CONSTRAINT invoice_items_service_types_id_fk FOREIGN KEY (service_type_id) REFERENCES field_operations.service_types(id);

ALTER TABLE ONLY billing.invoices
    ADD CONSTRAINT invoices_pestroutes_created_by_fk FOREIGN KEY (pestroutes_created_by) REFERENCES pestroutes.employees(id);

ALTER TABLE ONLY billing.invoices
    ADD CONSTRAINT invoices_pestroutes_customer_id_fk FOREIGN KEY (pestroutes_customer_id) REFERENCES customer.accounts(external_ref_id);

ALTER TABLE ONLY billing.invoices
    ADD CONSTRAINT invoices_pestroutes_subscription_id_fk FOREIGN KEY (pestroutes_subscription_id) REFERENCES customer.subscriptions(external_ref_id);

ALTER TABLE ONLY billing.invoices
    ADD CONSTRAINT invoices_service_types_external_ref_id_fk FOREIGN KEY (service_type_id) REFERENCES field_operations.service_types(id);

ALTER TABLE ONLY billing.invoices
    ADD CONSTRAINT invoices_service_types_id_fk FOREIGN KEY (pestroutes_service_type_id) REFERENCES field_operations.service_types(external_ref_id);

ALTER TABLE ONLY billing.ledger
    ADD CONSTRAINT ledger_account_id_fkey FOREIGN KEY (account_id) REFERENCES customer.accounts(id);

ALTER TABLE ONLY billing.ledger
    ADD CONSTRAINT ledger_autopay_payment_method_id_fkey FOREIGN KEY (autopay_payment_method_id) REFERENCES billing.payment_methods(id);

ALTER TABLE ONLY billing.ledger_transactions
    ADD CONSTRAINT ledger_transactions_account_id_fkey FOREIGN KEY (account_id) REFERENCES customer.accounts(id);

ALTER TABLE ONLY billing.ledger_transactions
    ADD CONSTRAINT ledger_transactions_invoice_id_fkey FOREIGN KEY (invoice_id) REFERENCES billing.invoices(id) ON DELETE CASCADE;

ALTER TABLE ONLY billing.ledger_transactions
    ADD CONSTRAINT ledger_transactions_payment_id_fkey FOREIGN KEY (payment_id) REFERENCES billing.payments(id) ON DELETE CASCADE;

ALTER TABLE ONLY billing.payment_invoice_allocations
    ADD CONSTRAINT payment_invoice_allocations_invoices_id_fk FOREIGN KEY (invoice_id) REFERENCES billing.invoices(id);

ALTER TABLE ONLY billing.payment_invoice_allocations
    ADD CONSTRAINT payment_invoice_allocations_payments_external_ref_id_fk FOREIGN KEY (pestroutes_payment_id) REFERENCES billing.payments(external_ref_id);

ALTER TABLE ONLY billing.payment_invoice_allocations
    ADD CONSTRAINT payment_invoice_allocations_payments_id_fk FOREIGN KEY (payment_id) REFERENCES billing.payments(id);

ALTER TABLE ONLY billing.payment_invoice_allocations
    ADD CONSTRAINT payment_invoice_allocations_pestroutes_invoice_id_fk FOREIGN KEY (pestroutes_invoice_id) REFERENCES billing.invoices(external_ref_id);

ALTER TABLE ONLY billing.payment_methods
    ADD CONSTRAINT payment_methods_payment_gateway_id_fkey FOREIGN KEY (payment_gateway_id) REFERENCES billing.payment_gateways(id);

ALTER TABLE ONLY billing.payment_methods
    ADD CONSTRAINT payment_methods_payment_type_id_fkey FOREIGN KEY (payment_type_id) REFERENCES billing.payment_types(id);

ALTER TABLE ONLY billing.payment_methods
    ADD CONSTRAINT payment_methods_pestroutes_created_by_fk FOREIGN KEY (pestroutes_created_by) REFERENCES pestroutes.employees(id);

ALTER TABLE ONLY billing.payment_methods
    ADD CONSTRAINT payment_methods_pestroutes_customer_id_fk FOREIGN KEY (pestroutes_customer_id) REFERENCES customer.accounts(external_ref_id);

ALTER TABLE ONLY billing.payments
    ADD CONSTRAINT payments_accounts_id_fk FOREIGN KEY (account_id) REFERENCES customer.accounts(id);

ALTER TABLE ONLY billing.payments
    ADD CONSTRAINT payments_original_payment_id_fkey FOREIGN KEY (original_payment_id) REFERENCES billing.payments(id);

ALTER TABLE ONLY billing.payments
    ADD CONSTRAINT payments_payment_gateway_id_fkey FOREIGN KEY (payment_gateway_id) REFERENCES billing.payment_gateways(id);

ALTER TABLE ONLY billing.payments
    ADD CONSTRAINT payments_payment_status_id_fkey FOREIGN KEY (payment_status_id) REFERENCES billing.payment_statuses(id);

ALTER TABLE ONLY billing.payments
    ADD CONSTRAINT payments_payment_type_id_fkey FOREIGN KEY (payment_type_id) REFERENCES billing.payment_types(id);

ALTER TABLE ONLY billing.payments
    ADD CONSTRAINT payments_pestroutes_created_by_fk FOREIGN KEY (pestroutes_created_by) REFERENCES pestroutes.employees(id);

ALTER TABLE ONLY billing.payments
    ADD CONSTRAINT payments_pestroutes_customer_id_fk FOREIGN KEY (pestroutes_customer_id) REFERENCES customer.accounts(external_ref_id);

ALTER TABLE ONLY billing.payments
    ADD CONSTRAINT payments_pestroutes_original_payment_id_fkey FOREIGN KEY (pestroutes_original_payment_id) REFERENCES billing.payments(external_ref_id);

ALTER TABLE ONLY billing.transactions
    ADD CONSTRAINT transactions_decline_reason_id_fkey FOREIGN KEY (decline_reason_id) REFERENCES billing.decline_reasons(id);

ALTER TABLE ONLY billing.transactions
    ADD CONSTRAINT transactions_payments_id_fk FOREIGN KEY (payment_id) REFERENCES billing.payments(id);

ALTER TABLE ONLY billing.transactions
    ADD CONSTRAINT transactions_transaction_types_id_fk FOREIGN KEY (transaction_type_id) REFERENCES billing.transaction_types(id);

ALTER TABLE ONLY customer.accounts
    ADD CONSTRAINT accounts_areas_id_fk FOREIGN KEY (area_id) REFERENCES field_operations.areas(id);

ALTER TABLE ONLY customer.accounts
    ADD CONSTRAINT accounts_billing_address_id_fkey FOREIGN KEY (billing_address_id) REFERENCES customer.addresses(id);

ALTER TABLE ONLY customer.accounts
    ADD CONSTRAINT accounts_billing_contact_id_fkey FOREIGN KEY (billing_contact_id) REFERENCES customer.contacts(id);

ALTER TABLE ONLY customer.accounts
    ADD CONSTRAINT accounts_contact_id_fkey FOREIGN KEY (contact_id) REFERENCES customer.contacts(id);

ALTER TABLE ONLY customer.accounts
    ADD CONSTRAINT accounts_dealer_id_fkey FOREIGN KEY (dealer_id) REFERENCES organization.dealers(id);

ALTER TABLE ONLY customer.accounts
    ADD CONSTRAINT accounts_pestroutes_created_by_fk FOREIGN KEY (pestroutes_created_by) REFERENCES pestroutes.employees(id);

ALTER TABLE ONLY customer.accounts
    ADD CONSTRAINT accounts_service_address_id_fkey FOREIGN KEY (service_address_id) REFERENCES customer.addresses(id);

ALTER TABLE ONLY customer.documents
    ADD CONSTRAINT documents_accounts_id_fk FOREIGN KEY (account_id) REFERENCES customer.accounts(id);

ALTER TABLE ONLY customer.notes
    ADD CONSTRAINT notes_cancellation_reason_id_fkey FOREIGN KEY (cancellation_reason_id) REFERENCES customer.cancellation_reasons(id);

ALTER TABLE ONLY customer.notes
    ADD CONSTRAINT notes_note_type_id_fkey FOREIGN KEY (note_type_id) REFERENCES customer.note_types(id);

ALTER TABLE ONLY customer.notes
    ADD CONSTRAINT notes_pestroutes_created_by_fk FOREIGN KEY (pestroutes_created_by) REFERENCES pestroutes.employees(id);

ALTER TABLE ONLY customer.notes
    ADD CONSTRAINT notes_pestroutes_customer_id_fk FOREIGN KEY (pestroutes_customer_id) REFERENCES customer.accounts(external_ref_id);

ALTER TABLE ONLY customer.subscriptions
    ADD CONSTRAINT subscriptions_pestroutes_created_by_fk FOREIGN KEY (pestroutes_created_by) REFERENCES pestroutes.employees(id);

ALTER TABLE ONLY customer.subscriptions
    ADD CONSTRAINT subscriptions_pestroutes_customer_id_fk FOREIGN KEY (pestroutes_customer_id) REFERENCES customer.accounts(external_ref_id);

ALTER TABLE ONLY customer.subscriptions
    ADD CONSTRAINT subscriptions_pestroutes_service_type_id_fk FOREIGN KEY (pestroutes_service_type_id) REFERENCES field_operations.service_types(external_ref_id);

ALTER TABLE ONLY customer.subscriptions
    ADD CONSTRAINT subscriptions_pestroutes_sold_by_fk FOREIGN KEY (pestroutes_sold_by) REFERENCES pestroutes.employees(id);

ALTER TABLE ONLY field_operations.appointments
    ADD CONSTRAINT appointments_appointment_type_id_fkey FOREIGN KEY (appointment_type_id) REFERENCES field_operations.appointment_types(id);

ALTER TABLE ONLY field_operations.appointments
    ADD CONSTRAINT appointments_pestroutes_cancelled_by_fk FOREIGN KEY (pestroutes_cancelled_by) REFERENCES pestroutes.employees(id);

ALTER TABLE ONLY field_operations.appointments
    ADD CONSTRAINT appointments_pestroutes_completed_by_fk FOREIGN KEY (pestroutes_completed_by) REFERENCES pestroutes.employees(id);

ALTER TABLE ONLY field_operations.appointments
    ADD CONSTRAINT appointments_pestroutes_created_by_fk FOREIGN KEY (pestroutes_created_by) REFERENCES pestroutes.employees(id);

ALTER TABLE ONLY field_operations.appointments
    ADD CONSTRAINT appointments_pestroutes_customer_id_fk FOREIGN KEY (pestroutes_customer_id) REFERENCES customer.accounts(external_ref_id);

ALTER TABLE ONLY field_operations.appointments
    ADD CONSTRAINT appointments_pestroutes_invoice_id_fk FOREIGN KEY (pestroutes_invoice_id) REFERENCES billing.invoices(external_ref_id);

ALTER TABLE ONLY field_operations.appointments
    ADD CONSTRAINT appointments_pestroutes_route_id_fk FOREIGN KEY (pestroutes_route_id) REFERENCES field_operations.routes(external_ref_id);

ALTER TABLE ONLY field_operations.appointments
    ADD CONSTRAINT appointments_pestroutes_service_type_id_fk FOREIGN KEY (pestroutes_service_type_id) REFERENCES field_operations.service_types(external_ref_id);

ALTER TABLE ONLY field_operations.appointments
    ADD CONSTRAINT appointments_pestroutes_serviced_by_fk FOREIGN KEY (pestroutes_serviced_by) REFERENCES pestroutes.employees(id);

ALTER TABLE ONLY field_operations.appointments
    ADD CONSTRAINT appointments_pestroutes_subscription_id_fk FOREIGN KEY (pestroutes_subscription_id) REFERENCES customer.subscriptions(external_ref_id);

ALTER TABLE ONLY field_operations.appointments
    ADD CONSTRAINT appointments_status_id_fkey FOREIGN KEY (status_id) REFERENCES field_operations.appointment_statuses(id);

ALTER TABLE ONLY field_operations.appointments
    ADD CONSTRAINT appointments_subscription_id_fkey FOREIGN KEY (subscription_id) REFERENCES customer.subscriptions(id);

ALTER TABLE ONLY field_operations.notification_recipient_type
    ADD CONSTRAINT fk_recipient FOREIGN KEY (notification_recipient_id) REFERENCES field_operations.notification_recipients(id);

ALTER TABLE ONLY field_operations.notification_recipient_type
    ADD CONSTRAINT fk_type FOREIGN KEY (type_id) REFERENCES field_operations.notification_types(id);

ALTER TABLE ONLY field_operations.markets
    ADD CONSTRAINT markets_region_id_fkey FOREIGN KEY (region_id) REFERENCES field_operations.regions(id);

ALTER TABLE ONLY field_operations.office_days_participants
    ADD CONSTRAINT office_days_participants_schedule_id_fkey FOREIGN KEY (schedule_id) REFERENCES field_operations.office_days_schedule(id);

ALTER TABLE ONLY field_operations.office_days_schedule_overrides
    ADD CONSTRAINT office_days_schedule_overrides_schedule_id_fkey FOREIGN KEY (schedule_id) REFERENCES field_operations.office_days_schedule(id);

ALTER TABLE ONLY field_operations.route_stats
    ADD CONSTRAINT optimization_state_id_fkey FOREIGN KEY (optimization_state_id) REFERENCES field_operations.optimization_states(id) ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE ONLY field_operations.routes
    ADD CONSTRAINT routes_areas_id_fk FOREIGN KEY (area_id) REFERENCES field_operations.areas(id);

ALTER TABLE ONLY field_operations.routes
    ADD CONSTRAINT routes_pestroutes_created_by_fk FOREIGN KEY (pestroutes_created_by) REFERENCES pestroutes.employees(id);

ALTER TABLE ONLY field_operations.routes
    ADD CONSTRAINT routes_route_group_id_fkey FOREIGN KEY (route_group_id) REFERENCES field_operations.route_groups(id);

ALTER TABLE ONLY field_operations.routes
    ADD CONSTRAINT routes_route_template_id_fk FOREIGN KEY (route_template_id) REFERENCES field_operations.route_templates(id);

ALTER TABLE ONLY field_operations.routes
    ADD CONSTRAINT routes_user_assigned_to_fkey FOREIGN KEY (user_assigned_to) REFERENCES organization.users(id) ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE ONLY field_operations.service_types
    ADD CONSTRAINT service_types_new_plan_id_fkey FOREIGN KEY (plan_id) REFERENCES product.plans(id);

ALTER TABLE ONLY licensing.counties
    ADD CONSTRAINT counties_state_id_fkey FOREIGN KEY (state_id) REFERENCES licensing.states(id);

ALTER TABLE ONLY licensing.municipalities
    ADD CONSTRAINT municipalities_state_id_fkey FOREIGN KEY (state_id) REFERENCES licensing.states(id);

ALTER TABLE ONLY licensing.user_county_licenses
    ADD CONSTRAINT user_county_licenses_county_id_fkey FOREIGN KEY (county_id) REFERENCES licensing.counties(id);

ALTER TABLE ONLY licensing.user_municipality_licenses
    ADD CONSTRAINT user_municipality_licenses_municipality_id_fkey FOREIGN KEY (municipality_id) REFERENCES licensing.municipalities(id);

ALTER TABLE ONLY licensing.user_state_licenses
    ADD CONSTRAINT user_state_licenses_state_id_fkey FOREIGN KEY (state_id) REFERENCES licensing.states(id);

ALTER TABLE ONLY notifications.logs_email
    ADD CONSTRAINT fk_logs_email_log_id_logs_id FOREIGN KEY (log_id) REFERENCES notifications.logs(id);

ALTER TABLE ONLY notifications.sms_messages_media
    ADD CONSTRAINT fk_sms_messages_media_sms_message_id_sms_messages_id FOREIGN KEY (sms_message_id) REFERENCES notifications.sms_messages(id) ON DELETE SET NULL;

ALTER TABLE ONLY organization.user_dealers
    ADD CONSTRAINT user_dealers_dealer_id_fkey FOREIGN KEY (dealer_id) REFERENCES organization.dealers(id);

ALTER TABLE ONLY organization.user_dealers
    ADD CONSTRAINT user_dealers_user_id_fkey FOREIGN KEY (user_id) REFERENCES organization.users(id) ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE ONLY pestroutes.etl_execution_steps
    ADD CONSTRAINT etl_execution_steps_execution_id_fkey FOREIGN KEY (execution_id) REFERENCES pestroutes.etl_executions(id);

ALTER TABLE ONLY sales.team_territories
    ADD CONSTRAINT team_territories_team_id_fkey FOREIGN KEY (team_id) REFERENCES sales.teams(id);

ALTER TABLE ONLY sales.team_territories
    ADD CONSTRAINT team_territories_territory_id_fkey FOREIGN KEY (territory_id) REFERENCES sales.territories(id);

ALTER TABLE ONLY sales.user_teams
    ADD CONSTRAINT user_teams_team_id_fkey FOREIGN KEY (team_id) REFERENCES sales.teams(id);

ALTER TABLE ONLY sales.user_teams
    ADD CONSTRAINT user_teams_user_id_fkey FOREIGN KEY (user_id) REFERENCES organization.users(id) ON UPDATE CASCADE ON DELETE CASCADE;

CREATE PUBLICATION airbyte_publication WITH (publish = 'insert, update, delete, truncate');

CREATE PUBLICATION billing WITH (publish = 'insert, update, delete, truncate');

CREATE PUBLICATION pins WITH (publish = 'insert, update, delete, truncate');

ALTER PUBLICATION billing ADD TABLE ONLY billing.payments;

ALTER PUBLICATION pins ADD TABLE ONLY street_smarts.pins;

CREATE EVENT TRIGGER awsdms_intercept_ddl ON ddl_command_end
   EXECUTE FUNCTION public.awsdms_intercept_ddl();

CREATE EVENT TRIGGER ddl_event_trigger_event ON ddl_command_end
         WHEN TAG IN ('CREATE TABLE')
   EXECUTE FUNCTION public.ddl_event_trigger();

