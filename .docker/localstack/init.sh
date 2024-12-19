#!/bin/bash

export AWS_DEFAULT_REGION=us-east-1

# Create SQS queues
awslocal sqs create-queue --queue-name collect-metrics-queue --region $AWS_DEFAULT_REGION
awslocal sqs create-queue --queue-name process-payments-queue --region $AWS_DEFAULT_REGION
awslocal sqs create-queue --queue-name process-failed-jobs-queue --region $AWS_DEFAULT_REGION
awslocal sqs create-queue --queue-name notifications-queue --region $AWS_DEFAULT_REGION
awslocal sqs create-queue --queue-name account-updater-queue --region $AWS_DEFAULT_REGION

# Create DynamoDB tables
awslocal dynamodb create-table --table-name pestroutes-credentials \
    --attribute-definitions AttributeName=office_id,AttributeType=N \
    --key-schema AttributeName=office_id,KeyType=HASH \
    --provisioned-throughput ReadCapacityUnits=5,WriteCapacityUnits=5 \
    --region $AWS_DEFAULT_REGION

awslocal dynamodb create-table --table-name worldpay-credentials \
    --attribute-definitions AttributeName=office_id,AttributeType=N \
    --key-schema AttributeName=office_id,KeyType=HASH \
    --provisioned-throughput ReadCapacityUnits=5,WriteCapacityUnits=5 \
    --region $AWS_DEFAULT_REGION

# Create S3 buckets
awslocal s3api create-bucket --bucket account-updater-files --region $AWS_DEFAULT_REGION

# Populate DynamoDB tables with data
if ls /mnt/dynamodb-data/pestroutes-credentials*.json 1> /dev/null 2>&1; then
    echo "Found files for DynamoDB pestroutes-credentials table populating"
    for creds in /mnt/dynamodb-data/pestroutes-credentials*.json; do
        echo "Populating credentials from $creds"
        awslocal dynamodb batch-write-item --request-items file:///$creds
    done
else
    echo "No files for DynamoDB pestroutes-credentials table populating were found, skipping"
fi

if ls /mnt/dynamodb-data/worldpay-credentials*.json 1> /dev/null 2>&1; then
    echo "Found files for DynamoDB worldpay-credentials table populating"
    for creds in /mnt/dynamodb-data/worldpay-credentials*.json; do
        echo "Populating credentials from $creds"
        awslocal dynamodb batch-write-item --request-items file:///$creds
    done
else
    echo "No files for DynamoDB worldpay-credentials table populating were found, skipping"
fi
