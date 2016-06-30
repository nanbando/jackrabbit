# Nanbando: jackrabbit

Nanando-Plugin which uses `phpcr` to backup and restore jackrabbit data.

## Installation

You can install this plugin by adding `nanbando/jackrabbit` to the `require`-section of the nanbando.json file.

## Configuration

```json
{
    "name": "application",
    "backup": {
        "cmf": {
            "plugin": "jackrabbit",
            "parameter": {
                "jackrabbit_uri": "http://localhost:8080/server/",
                "workspace": "default",
                "user": "admin",
                "password": "***",
                "path": "/cmf"
            }
        },
    },
    "require": {
        "nanbando/jackrabbit": "^0.1"
    }
}
```

## Documentation

See the official documentation on [nanbando.readthedocs.io/en/latest/plugins/index.html](https://nanbando.readthedocs.io/en/latest/plugins/index.html).
