# viaPhone

## Installation

via composer:

```sh
composer require adt/viaphone
```

and in config.neon:

```neon
services:
	- ADT\Viaphone\SmsGateway(%smsSender.apiKey%)

parameters: 
	smsSender:
		apiKey: xxx
```
