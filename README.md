# Simple JSON Store

[![Author](https://img.shields.io/badge/author-falkm-blue.svg?style=flat-square)](https://falk-m.de)
[![Source Code](http://img.shields.io/badge/source-falkmueller/hubert-blue.svg?style=flat-square)](https://github.com/falkmueller/jsonstore)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

Simple PHP Json object store.
You can use it as a simple flat file CMS or a Headless CMS Backend

## Features

- HTTP basic auth
- Restful api
- directory listing
- filterable resource lists

## contiguration

- copy "config.php" to "config.loacal.php"
- change admin password (defauld is "admin") or add new user

```php

return [
    "user" => [
      "admin" => ["name" => "Administrator", "password" => "admin"]
    ],
];

```

## Usage

### create a resource

```
POST /list1/object1.json
```
```json
{
  "name": "test",
  "hobbies": ["football", "tennis"]
}
```


### create or override complete resource

```
PUT /list1/object1.json
```
```json
{
  "name": "blub",
  "hobbies": ["football"]
}
```


### update partial (resource must exists)

```
PATCH /list1/object1.json
```
```json
{
  "name": "test"
}
```

### Delete a resource

```
DELETE /list1/object1.json
```

### Get a single resource

```
GET /list1/object1.json
```

### Get a list of resources and sub directories

```
GET /list1
```

Response:

```json
{
  "resources": ["object1.json"],
  "directories": ["some_sub_dir"]
}
```
#### Filter

- equals
  - ```GET /list1?filter[0][field]=name&filter[0][op]=equal&filter[0][value]=test```
- string contains a part
  - ```GET /list1?filter[0][field]=name&filter[0][op]=contains&filter[0][value]=es```
- array contains value
  - ```GET /list1?filter[0][field]=hobbies&filter[0][op]=contains&filter[0][value]=tennis```
