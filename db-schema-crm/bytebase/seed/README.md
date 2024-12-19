# System Records

As illustrated in this document: https://aptive.atlassian.net/l/cp/Z1MGxYGy we need to ensure that the database records that are tied to application logic are held in version control and can be used to seed the database with those records. Thus we use the `system_records.sql` file to hold that data.

The sql files here can easily be run on local or can be submitted to a bytebase repo as a DML file to seed the records on higher tier environments.

> **Note:** You can easily create a dml file from this file using this command: `php artisan dml:make`

## Why not use Bytebase DML?

Unfortunately we cannot use just raw bytebase DML files as they will be removed as part of the bytebase flow and will be lost from version control. Also we need to be free to use DML files to perform changes to data as part of managing breaking changes to database schema.

## How to use sql files in this directory?

You can easily apply these sql files on your local using common database migration tools. You can also apply them to higher tier enviornments by submitting a pull request to a bytebase repository with the DML .sql file needed and naming it appropriately.