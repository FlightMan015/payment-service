Here you can find information on how to contribute to this project.

# Environment Setup

1. Ensure that you have docker desktop and docker compose tools installed on your host machine

   https://docs.docker.com/desktop/
   https://docs.docker.com/compose/install/linux/#install-the-plugin-manually

2. Copy the `compose.override.yml.example` into a new `compose.override.yml` file. Make any needed adjustments.
3. Copy these `pestroutes-credentials.json.example` and `worldpay-credentials.json.example` files from `.docker/localstack/dynamodb` to their respective names without the `.example` extension to the same folder and replace the actual credentials needed for your environment.
    1. In case there are more than 25 items in your JSON file, you will need to split it to different files. The names should be `pestroutes-credentials-1.json`, `pestroutes-credentials-2.json`, etc.
4. Add an auth.json file to the root directory to authenticate to Aptive Composer Packages Repository for local development
    1. Reference: https://aptive.atlassian.net/wiki/spaces/EN/pages/1524924437/Installing+a+Custom+Composer+Package+-+Guide#auth.json
5. Build and start up the containers in the stack using this command

```sh
    docker compose up --build
```

6. The local development environment can be accessed at this url: http://locahost:8080 (port should be exposed in `compose.override.yml`)

# Contribution Process
1. Create a feature or bugfix branch for your changes following gitlab flow based on the develop branch
    * GitHub Flow: https://aptive.atlassian.net/wiki/x/AQDvX
2. Ensure any changes you deliver have Unit Tests which are passing
    * From within the container terminal you can run the tests via this command "composer test"
3. Push your branch to the remote repo and ensure that the CI pipeline is passing on your branch
4. Submit a Pull Request and the Code Owners will review your changes
    * Be sure to be extremely descriptive with your PR and follow this guide: https://aptive.atlassian.net/l/cp/4ng0MBYE
5. Work with the Reviewer on any requested changes
