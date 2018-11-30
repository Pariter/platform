# Pariter's platform

Source of the main web platform

For now it's using:
- Phalcon (PHP Framework)
- Bootstrap (CSS Framework)

# Development (with Docker)
If you want to help, we've setup Docker configuration for you.

## Installing Docker
1. Install Docker CE: https://docs.docker.com/install/
2. Install Docker Compose: https://docs.docker.com/compose/install/ (for Linux users: «Alternative Install Options» offers the `pip` alternative)

## Add non-root setup
https://docs.docker.com/install/linux/linux-postinstall/#manage-docker-as-a-non-root-user

## Create the project
1. Check out this repository: git clone https://github.com/Pariter/platform.git
2. Change directory to the cloned repository: cd platform
2. Edit docker-compose.yml to suit your needs: editor docker-compose.yml
3. Start the container and post-install scripts: sh docker.sh start (please read the file before running it)
5. Stop the container: sh docker.sh stop

## Side note about post-installation script
There's no easy option to run a post-installation script on Docker with docker-compose.yml. See https://github.com/docker/compose/issues/1809 for instance.