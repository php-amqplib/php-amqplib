# Contributing

Contributions are **welcome** and will be fully **credited**.

We accept contributions via Pull Requests on [Github](https://github.com/php-amqplib/php-amqplib).


## Pull Requests

- **[PSR-2 Coding Standard](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)** - The easiest way to apply the conventions is to install [PHP Code Sniffer](http://pear.php.net/package/PHP_CodeSniffer).

- **Add tests!** - Your patch won't be accepted if it doesn't have tests.

- **Document any change in behaviour** - Make sure the README and any other relevant documentation are kept up-to-date.

- **Consider our release cycle** - We try to follow semver. Randomly breaking public APIs is not an option.

- **Create topic branches** - Don't ask us to pull from your master branch.

- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

- **Send coherent history** - Make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please squash them before submitting.


## Running Tests

### Start RabbitMQ

To successfully run the tests you need to first have a stock RabbitMQ broker running locally

* Using `docker compose`
    ```
    make docker-test-env
    ```
    Note: if you wish to ensure the latest docker images, run this command:
    ```
    make DOCKER_FRESH=true clean docker-test-env
    ```
* Using the GitHub Actions setup script
    Note: this has been tested on Ubuntu 22 and Arch Linux
    ```
    ./.ci/ubuntu/gha-setup.sh
    ```

### Run Tests

* Using an environment started via `make docker-test-env`:
    ```
    make docker-test
    ```
* Using a local RabbitMQ started by `gha-setup.sh`:
    ``` bash
    make test
    ```

### Cleanup / Troubleshooting

If you started your environment using `make docker-test-env`, use `docker compose stop` to stop it.

If you ran tests locally, but then decided to use the docker environment to run tests, you should first run `make clean`

**Happy coding**!
