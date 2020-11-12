# Itspire - Monolog Loki

This library follows the PSR-12 convention.

# Usage

## Recommended Usage

Since Loki log handling uses a remote server, logging is prone to be subject of timeout, network shortage and so on.
To avoid your application being broken in such a case, we recommend wrapping the handler in a WhatFailureGroupHandler

## Native
```php
use Itspire\MonologLoki\Handler\LokiHandler;
use Monolog\Handler\WhatFailureGroupHandler;

$handler = new WhatFailureGroupHandler(
    [
        new LokiHandler(
            [
                'entrypoint' => 'https://loki:3100',
                'context' => [
                    // Set here your globally applicable context variables
                ],
                'labels' => [
                    // Set here your globally applicable labels
                ],
                'client_name' => 'your_host_name', // Here set a unique identifier for the client host
                // Optional : if you're using basic auth to authentify
                'auth' => [
                    'basic' => ['user', 'password'],
                ],
            ]
        )
    ]
);
```

## Symfony App

### Configure LokiHandler Service
```yaml
  Itspire\MonologLoki\Handler\LokiHandler:
    arguments:
      $apiConfig:
        entrypoint: 'http://loki:3100'
        context:
          app: My-app
        labels:
          env: '%env(APP_ENV)%'
        client_name: my_app_server
```
Note : 
We're currently working on a possible bundle based implementation for Symfony but at the moment, this is the way.


### Configure Monolog to use Loki Handler

```yaml
monolog:
  handlers:
    loki:
      type: service
      id: Itspire\MonologLoki\Handler\LokiHandler

    my_loki_handler:
      type:   whatfailuregroup
      members: [loki]
      level: debug
      process_psr_3_messages: true # optional but we find it rather useful (Note : native handler required to use)
```

# Testing
In order to test using the provided docker-compose file, you'll need an up-to-date docker/docker-compose installation
You can start the Loki container by navigating to src/main/test/docker and running 
```shell script
docker-compose up
```

If you're testing from a local php installation, you'll need to retrieve the Loki container ip with :
```shell script
docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' itspire-monolog-loki_loki_1
```

If you're testing from containerized php, you'll need to start the container with an extra host named Loki 
mapped to your current host ip, using the following option :
```shell script
--add-host loki:{your_host_ip}
```

Run the test using phpunit and you can verify that posting to Loki works
by running the following from your host terminal : 
```shell script
curl -G -s  "http://localhost:3100/loki/api/v1/query" --data-urlencode 'query={channel="test"}' | jq
```

For each time you ran the tests, you should see a log entry looking like the following : 
```json
{
    "stream": {
        "channel": "test",
        "host": "f2bbe48b0204",
        "level_name": "WARNING"
    },
    "values": [
        [
        "1591627127000000000",
        "{\"message\":\"test\",\"level\":300,\"level_name\":\"WARNING\",\"channel\":\"test\",\"datetime\":\"2020-06-08 14:38:47\",\"ctxt_data\":\"[object] (stdClass: {})\",\"ctxt_foo\":\"34\"}"
        ]
    ]
}
```
