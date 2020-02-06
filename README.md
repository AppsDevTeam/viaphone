# ViaPhone

## Installation

via composer:

```sh
composer require adt/viaphone
```

and in config.neon:

```neon
services:
	- ADT\Viaphone\Viaphone(%viaphone.apiKey%)

parameters: 
	smsSender:
		apiKey: xxx
```
