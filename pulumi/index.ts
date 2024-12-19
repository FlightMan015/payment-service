import * as pulumi from "@pulumi/pulumi";
import * as aws from "@pulumi/aws";
import * as k8s from "@pulumi/kubernetes";

const config = new pulumi.Config();

type ResourceTags = Record<string, string>;
const tags: ResourceTags = {
    'aptive:github-url': 'https://github.com/aptive-env/payment-service',
    'aptive:third-party': 'false',
    'aptive:configured-with': 'pulumi',
    'aptive:compliance': 'false',
    'aptive:region': 'us-east-1',
    'aptive:owner-team-id': 'payments'
}

const env = pulumi.getStack();

// get the k8s provider

const k8sStackReference = new pulumi.StackReference(`aptive/ops-eks/${config.require("eksStackName")}`);
const clusterName = config.require("eksStackName");
const k8sProvider = new k8s.Provider('k8s-provider', {});
const secretArn = k8sStackReference.getOutput('chartOutputs').apply(chartOutputs => chartOutputs.externalSecretsRoleArn);

const version = process.env.IMAGE_TAG;

if(!version) {
    throw new Error("env var IMAGE_TAG is required");
}

const imageRepo = "986611149894.dkr.ecr.us-east-1.amazonaws.com/payment-service/api:" + version;

/******** AWS *********/

const secret = new aws.secretsmanager.Secret('payment-service-secret', {
    name: `${env}-payment-service-secret`,
    tags:  {
        ...tags,
        [`eks-cluster/${clusterName}`]: 'owned'
    }
});

const secretVersion = new aws.secretsmanager.SecretVersion('payment-service-secret-version', {
    secretId: secret.id,
    secretString: `{"DB_PASSWORD": ""}`
});

const processPaymentsQueue = new aws.sqs.Queue("payment-service-process-payments", {
    name: `${env}-payment-service-process-payments`,
    tags: tags
});

const collectMetricsQueue  = new aws.sqs.Queue("payment-service-collect-metrics", {
    name: `${env}-payment-service-collect-metrics`,
    tags: tags
});

const notificationsQueue  = new aws.sqs.Queue("payment-service-notifications", {
    name: `${env}-payment-service-notifications`,
    tags: tags
});

const processFailedJobsQueue = new aws.sqs.Queue("payment-service-process-failed-jobs", {
    name: `${env}-payment-service-process-failed-jobs`,
    tags: tags
});


let policyDocument: aws.iam.PolicyDocument = {
    Version: "2012-10-17",
    Statement: [
        {
            Action: ["dynamodb:Get*"],
            Effect: "Allow",
            Resource: [
                config.require("officeCredentialsTableArn"),
                config.require("officeCredentialsWorldPayTableArn")
            ],
        },
        {
            Effect: "Allow",
            Action: [
                "sqs:DeleteMessage",
                "sqs:GetQueueAttributes",
                "sqs:GetQueueUrl",
                "sqs:PurgeQueue",
                "sqs:ReceiveMessage",
                "sqs:SendMessage",
            ],
            Resource: [
                processPaymentsQueue.arn,
                collectMetricsQueue.arn,
                notificationsQueue.arn,
                processFailedJobsQueue.arn,
            ],
        },
        {
            Effect: "Allow",
            Action: [
                "s3:GetObject",
                "s3:PutObject",
                "s3:GetObjectAcl",
                "s3:DeleteObject",
            ],
            Resource: [
                config.require("paymentsAccountUpdaterS3"),
                pulumi.interpolate`${config.require("paymentsAccountUpdaterS3")}/*`,
            ],
        },
        {
            Effect: "Allow",
            Action: [
                "sqs:ReceiveMessage",
                "sqs:DeleteMessage",
            ],
            Resource: [config.require("paymentsAccountUpdaterSQS"),]
        },
    ],
};

// Create the IAM policy
let policy = new aws.iam.Policy("payment-service-iam-policy", {
    policy: policyDocument,
    name: `${env}-payment-service-iam-policy`,
});

// create a service account IAM role
const iamRole = new aws.iam.Role('payment-service-iam-role', {
    name: `${env}-payment-service-sa-role`,
    assumeRolePolicy: pulumi.all([k8sStackReference.getOutput('clusterOidcProviderArn'), k8sStackReference.getOutput('clusterOidcProvider')]).apply(([arn, provider]) => JSON.stringify({
        Version: "2012-10-17",
        Statement: [{
            Effect: "Allow",
            Principal: {
                Federated: arn
            },
            Action: "sts:AssumeRoleWithWebIdentity",
            Condition: {
                StringEquals: {
                    [`${provider}:aud`]: "sts.amazonaws.com",
                },
            },
        }],
    })),
    tags: tags
});

// attach the policy to the role
const rolePolicyAttachment = new aws.iam.PolicyAttachment('payment-service-iam-policy-attachment', {
    policyArn: policy.arn,
    roles: [iamRole.name]
});

/******** K8S *********/

// create a namespace
const namespace = new k8s.core.v1.Namespace('payment-service-namespace', {
    metadata: {
        name: 'payment-service',
        labels: {
            name: 'payment-service'
        }
    }
}, { provider: k8sProvider });

// create a service account
const serviceAccount = new k8s.core.v1.ServiceAccount('payment-service-service-account', {
    metadata: {
        name: 'payment-service-service-account',
        namespace: namespace.metadata.name,
        annotations: {
            "eks.amazonaws.com/role-arn": iamRole.arn
        }
    }
}, { provider: k8sProvider });

// Secrets part 1 - secretstore
const secretStore = new k8s.apiextensions.CustomResource('payment-service-secretstore', {
    apiVersion: 'external-secrets.io/v1beta1',
    kind: 'SecretStore',
    metadata: {
        name: 'payment-service-secretstore',
        namespace: namespace.metadata.name
    },
    spec: {
        provider: {
            aws: {
                region: 'us-east-1',
                service: "SecretsManager",
                role: secretArn
            }
        }
    }
}, { provider: k8sProvider });

// Secrets part 2 - external secret
const externalSecret = new k8s.apiextensions.CustomResource('payment-service-external-secret', {
    apiVersion: 'external-secrets.io/v1beta1',
    kind: 'ExternalSecret',
    metadata: {
        name: 'payment-service-external-secret',
        namespace: namespace.metadata.name
    },
    spec: {
        refreshInterval: "5m",
        secretStoreRef: {
            name: secretStore.metadata.name,
            kind: "SecretStore"
        },
        target: {
            name: "payment-service-secrets",
            creationPolicy: "Owner"
        },
        dataFrom: [{
            extract: {
                key: secret.name
            }
        }]
    },
}, { provider: k8sProvider });

const configMapName = "payment-service-configmap-" + Date.now();
const configMap = new k8s.kustomize.Directory("payment-service-config-map", {
    directory: `../k8s/overlays/${pulumi.getStack()}`,
    transformations: [
        (obj: any, opts: pulumi.CustomResourceOptions) => {
            obj.metadata.name = configMapName;
            obj.data.SQS_PROCESS_PAYMENTS_QUEUE = processPaymentsQueue.name;
            obj.data.SQS_COLLECT_METRICS_QUEUE = collectMetricsQueue.name;
            obj.data.SQS_NOTIFICATIONS_QUEUE = notificationsQueue.name;
            obj.data.SQS_PROCESS_FAILED_JOBS_QUEUE = processFailedJobsQueue.name;
            obj.data.SLACK_ALERT_QUEUE = notificationsQueue.name;
            return obj;
        }
    ]
}, { provider: k8sProvider });

// create a deployment
const deployment = new k8s.apps.v1.Deployment('payment-service-deployment', {
    metadata: {
        namespace: namespace.metadata.name,
        name: 'payment-service',
        labels: {
            app: 'payment-service',
            "tags.datadoghq.com/env": config.require("datadogEnv"),
            "tags.datadoghq.com/service": "payment-service",
            "tags.datadoghq.com/version": version,
            "admission.datadoghq.com/enabled": "true",
            "app.kubernetes.io/name": `payment-service`,
        },
        annotations: {
            "configmap.reloader.stakater.com/reload": configMapName,
            "secret.reloader.stakater.com/reload": "payment-service-secrets",
            "ad.datadoghq.com/payment-service.logs": `[{"source": "container","service":"payment-service","auto_multi_line_detection": true,"tags":["env":"${config.require("datadogEnv")}]}]`,
        }
    },
    spec: {
        replicas: 2,
        revisionHistoryLimit: 1,
        selector: {
            matchLabels: {
                app: 'payment-service'
            }
        },
        template: {
            metadata: {
                labels: {
                    app: 'payment-service',
                    "tags.datadoghq.com/env": config.require("datadogEnv"),
                    "tags.datadoghq.com/service": "payment-service",
                    "tags.datadoghq.com/version": version,
                    "admission.datadoghq.com/enabled": "true",
                    "app.kubernetes.io/name": `payment-service`,
                },
                annotations: {
                    "ad.datadoghq.com/payment-service.logs": `[{"source": "container","service":"payment-service","auto_multi_line_detection": true,"tags":["env":"${config.require("datadogEnv")}]}]`,
                }
            },
            spec: {
                serviceAccountName: serviceAccount.metadata.name,
                containers: [{
                    name: 'payment-service',
                    image: `${imageRepo}`,
                    imagePullPolicy: 'Always',
                    envFrom: [{
                        configMapRef: {
                            name: configMapName
                        },
                    },{
                        secretRef: {
                            name: 'payment-service-secrets'
                        }
                    }],
                    ports: [{
                        name: 'http',
                        containerPort: 80,
                        protocol: 'TCP'
                    }],
                    livenessProbe: {
                        tcpSocket: {
                            port: 'http'
                        },
                        initialDelaySeconds: 5,
                        periodSeconds: 10
                    },
                    readinessProbe: {
                        tcpSocket: {
                            port: 'http'
                        },
                        initialDelaySeconds: 5,
                        periodSeconds: 10
                    },
                    resources: {
                        limits: {
                            memory: "256Mi"
                        },
                        requests: {
                            cpu: "100m",
                            memory: "256Mi"
                        }
                    }
                }],
                nodeSelector: {},
                affinity: {
                    nodeAffinity: {
                        preferredDuringSchedulingIgnoredDuringExecution: [
                            {
                                weight: 100,
                                preference: {
                                    matchExpressions: [
                                        {
                                            key: "node.kubernetes.io/distribution",
                                            operator: "In",
                                            values: [
                                                "spot"
                                            ]
                                        }
                                    ]
                                }
                            }
                        ]
                    }
                },
                tolerations: [],
                topologySpreadConstraints: [
                    {
                        maxSkew: 1,
                        topologyKey: "topoplogy.kubernetes.io/zone",
                        whenUnsatisfiable: "ScheduleAnyway",
                        labelSelector: {
                            matchLabels: {
                                "app.kubernetes.io/name": `payment-service`,
                            }
                        }
                    },
                ]
            }
        }
    }
}, { provider: k8sProvider });

// create a service
const service = new k8s.core.v1.Service('payment-service-service', {
    metadata: {
        namespace: namespace.metadata.name,
        name: 'payment-service',
        labels: {
            app: 'payment-service'
        }
    },
    spec: {
        selector: {
            app: 'payment-service'
        },
        ports: [{
            port: 80,
            targetPort: 80
        }]
    }
}, { provider: k8sProvider });

// pod autoscaler
const hpa = new k8s.autoscaling.v2.HorizontalPodAutoscaler('payment-service-hpa', {
    metadata: {
        namespace: namespace.metadata.name,
        name: 'payment-service',
        labels: {
            app: 'payment-service'
        }
    },
    spec: {
        scaleTargetRef: {
            apiVersion: "apps/v1",
            kind: "Deployment",
            name: deployment.metadata.name
        },
        minReplicas: 2,
        maxReplicas: 100,
        metrics: [
            {
                type: "Resource",
                resource: {
                    name: "cpu",
                    target: {
                        type: "Utilization",
                        averageUtilization: 80
                    }
                }
            },
            {
                type: "Resource",
                resource: {
                    name: "memory",
                    target: {
                        type: "Utilization",
                        averageUtilization: 80
                    }
                }
            }
        ]
    }
}, { provider: k8sProvider });

// disruption budget
const disruptionBudget = new k8s.policy.v1.PodDisruptionBudget('payment-service-pdb', {
    metadata: {
        namespace: namespace.metadata.name,
        name: 'payment-service',
        labels: {
            app: 'payment-service'
        }
    },
    spec: {
        minAvailable: "50%",
        selector: {
            matchLabels: {
                app: 'payment-service'
            }
        }
    }
}, { provider: k8sProvider });

//  rewrite the API path so the payment-service app can understand it
const apiPathMiddleware = new k8s.apiextensions.CustomResource("payment-service-api-path-middleware", {
    apiVersion: "traefik.containo.us/v1alpha1",
    kind: "Middleware",
    metadata: {
        name: "payment-service-api-url-rewrite",
        namespace: namespace.metadata.name
    },
    spec: {
        replacePathRegex: {
            regex: "^/payments/(.*)",
            replacement: "/api/$1"
        }
    }
}, { provider: k8sProvider });

// create a jwt auth middleware
const jwtAuthMiddleware = new k8s.apiextensions.CustomResource("payment-service-jwt-auth-middleware", {
    apiVersion: "traefik.containo.us/v1alpha1",
    kind: "Middleware",
    metadata: {
        name: "payment-service-jwt-auth-middleware",
        namespace: namespace.metadata.name
    },
    spec: {
        plugin: {
            jwtAuth: {
                source: "crmFusionAuthJwt",
                forwardHeaders: {
                    "Expires-At": "exp",
                    'Aptive-Api-Account-Id': "sub"
                },
                claims: "Equals(`scope`, `" + config.require("apiAuthScope") + "`)"
            }
        }
    }
}, { provider: k8sProvider });

// create an ingress route
const ingressRoute = new k8s.apiextensions.CustomResource("payment-service-ingress-route", {
    apiVersion: "traefik.io/v1alpha1",
    kind: "IngressRoute",
    metadata: {
        name: "payment-service-ingress-route",
        annotations: {
            "kubernetes.io/ingress.class": "traefik",
        },
    },
    spec: {
        entryPoints: ["web", "websecure"],
        routes: [
        {
            match: "Host(`" + config.require('apiHostname') + "`) && PathPrefix(`/payments`)",
            kind: "Rule",
            services: [{
                name: "payment-service",
                namespace: namespace.metadata.name,
                port: 80
            }],
            middlewares: [
                // temporarily removing jwt auth middleware until all services are updated to use it
                // {
                //     name: "payment-service-jwt-auth-middleware",
                //     namespace: namespace.metadata.name
                // },
            {
                name: "payment-service-api-url-rewrite",
                namespace: namespace.metadata.name
            },{
                name: "remove-cf-headers",
                namespace: "traefikee"
            }]
        }
        ],
        tls: {
            certResolver: "letsencrypt"
        }
    },
}, { provider: k8sProvider });

// create a service account to allow scheduled restart of queue consumers
// This will solve php long-running processes memory leaks
const queueConsumerServiceAccount = new k8s.core.v1.ServiceAccount('payment-service-queue-consumer-service-account', {
    metadata: {
        name: 'payment-service-queue-consumer-service-account',
        namespace: namespace.metadata.name
    }
}, { provider: k8sProvider });

// create a role to allow the service account to restart deployments
const queueConsumerRole = new k8s.rbac.v1.Role('payment-service-queue-consumer-role', {
    metadata: {
        name: 'payment-service-queue-consumer-role',
        namespace: namespace.metadata.name
    },
    rules: [{
        apiGroups: ["apps", "extensions"],
        resources: ["deployments"],
        verbs: ["get", "list", "patch", "watch"]
    }]
}, { provider: k8sProvider });

// create a role binding to attach the role to the service account
const queueConsumerRoleBinding = new k8s.rbac.v1.RoleBinding('payment-service-queue-consumer-role-binding', {
    metadata: {
        name: 'payment-service-queue-consumer-role-binding',
        namespace: namespace.metadata.name
    },
    roleRef: {
        apiGroup: "rbac.authorization.k8s.io",
        kind: "Role",
        name: queueConsumerRole.metadata.name
    },
    subjects: [{
        kind: "ServiceAccount",
        name: queueConsumerServiceAccount.metadata.name
    }]
}, { provider: k8sProvider });

// add deployments for queue consumers
const queueConsumers = [
    {
        name: "collect-metrics",
        command: "php artisan queue:work sqs --queue=${SQS_COLLECT_METRICS_QUEUE}",
        replicaCount: 1,
        restartSchedule: "30 23 * * *"
    },
    {
        name: "process-payments",
        command: "php artisan queue:work sqs --queue=${SQS_PROCESS_PAYMENTS_QUEUE}",
        replicaCount: 1,
        restartSchedule: "30 23 * * *"
    },
    {
        name: "notifications",
        command: "php artisan queue:work sqs --queue=${SQS_NOTIFICATIONS_QUEUE}",
        replicaCount: 1,
        restartSchedule: "30 23 * * *"
    },
    {
        name: "failed-jobs",
        command: "php artisan queue:work sqs --queue=${SQS_PROCESS_FAILED_JOBS_QUEUE}",
        replicaCount: 1,
        restartSchedule: "30 23 * * *"
    }
];

queueConsumers.forEach((consumer, index) => {
    const qcDeployment = new k8s.apps.v1.Deployment(`payment-service-queue-consumer-${consumer.name}`, {
        metadata: {
            namespace: namespace.metadata.name,
            name: `payment-service-queue-consumer-${consumer.name}`,
            labels: {
                app: `payment-service-queue-consumer-${consumer.name}`,
                "tags.datadoghq.com/env": config.require("datadogEnv"),
                "tags.datadoghq.com/service": `payment-service-queue-consumer-${consumer.name}`,
                "tags.datadoghq.com/version": version,
                "admission.datadoghq.com/enabled": "true",
                "app.kubernetes.io/name": `payment-service`,
            },
            annotations: {
                "configmap.reloader.stakater.com/reload": configMapName,
                "secret.reloader.stakater.com/reload": "payment-service-secrets",
                [`ad.datadoghq.com/payment-service-queue-consumer-${consumer.name}.logs`]: `[{"source": "container","service":"payment-service-queue-consumer-${consumer.name}","tags":["env":"${config.require("datadogEnv")}"]}]`,
            }
        },
        spec: {
            replicas: consumer.replicaCount,
            revisionHistoryLimit: 1,
            selector: {
                matchLabels: {
                    app: `payment-service-queue-consumer-${consumer.name}`
                }
            },
            template: {
                metadata: {
                    labels: {
                        app: `payment-service-queue-consumer-${consumer.name}`,
                        "tags.datadoghq.com/env": config.require("datadogEnv"),
                        "tags.datadoghq.com/service": `payment-service-queue-consumer-${consumer.name}`,
                        "tags.datadoghq.com/version": version,
                        "admission.datadoghq.com/enabled": "true",
                        "app.kubernetes.io/name": `payment-service`,
                    },
                    annotations: {
                        [`ad.datadoghq.com/payment-service-queue-consumer-${consumer.name}.logs`]: `[{"source": "container","service":"payment-service-queue-consumer-${consumer.name}","tags":["env":"${config.require("datadogEnv")}"]}]`,
                    }
                },
                spec: {
                    serviceAccountName: serviceAccount.metadata.name,
                    containers: [{
                        name: `payment-service-queue-consumer-${consumer.name}`,
                        image: `${imageRepo}`,
                        imagePullPolicy: 'Always',
                        command: ['/bin/sh', '-c'],
                        args: [consumer.command],
                        envFrom: [{
                            configMapRef: {
                                name: configMapName
                            },
                        },{
                            secretRef: {
                                name: 'payment-service-secrets'
                            }
                        }],
                        resources: {
                            limits: {
                                memory: "256Mi"
                            },
                            requests: {
                                cpu: "100m",
                                memory: "256Mi"
                            }
                        },
                    }],
                    nodeSelector: {},
                    affinity: {
                        nodeAffinity: {
                            preferredDuringSchedulingIgnoredDuringExecution: [
                                {
                                    weight: 100,
                                    preference: {
                                        matchExpressions: [
                                            {
                                                key: "node.kubernetes.io/distribution",
                                                operator: "In",
                                                values: [
                                                    "spot"
                                                ]
                                            }
                                        ]
                                    }
                                }
                            ]
                        }
                    },
                    tolerations: [],
                    topologySpreadConstraints: [
                        {
                            maxSkew: 1,
                            topologyKey: "topoplogy.kubernetes.io/zone",
                            whenUnsatisfiable: "ScheduleAnyway",
                            labelSelector: {
                                matchLabels: {
                                    "app.kubernetes.io/name": `payment-service`,
                                }
                            }
                        }
                    ]
                }
            }
        }
    }, { provider: k8sProvider });

    // cron to restart the deployment daily
    const cronJob = new k8s.batch.v1.CronJob(`payments-queue-consumer-restart-${consumer.name}`, {
        metadata: {
            namespace: namespace.metadata.name,
            name: `payments-queue-consumer-restart-${consumer.name}`,
            labels: {
                app: `payments-queue-consumer-${consumer.name}`
            }
        },
        spec: {
            successfulJobsHistoryLimit: 1,
            failedJobsHistoryLimit: 2,
            concurrencyPolicy: "Forbid",
            schedule: consumer.restartSchedule,
            jobTemplate: {
                spec: {
                    backoffLimit: 2,
                    activeDeadlineSeconds: 600,
                    template: {
                        spec: {
                            serviceAccountName: queueConsumerServiceAccount.metadata.name,
                            restartPolicy: "Never",
                            containers: [{
                                name: "kubectl",
                                image: "bitnami/kubectl:latest",
                                command: ["kubectl" , "rollout", "restart", "deployment", `payments-queue-consumer-${consumer.name}`],
                            }],
                        }
                    }
                }
            }
        }
    }, { provider: k8sProvider });
});
