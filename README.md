# ViaPhone

## Installation

via composer:

```sh
composer require adt/viaphone
```

and in config.neon:

```neon
services:
	- ADT\ViaPhone\ViaPhone(%viaPhone.secret%)

parameters: 
	viaPhone:
		secret: xxx
```
Usage
---------
$viaPhone = ADT\ViaPhone\ViaPhone('apiKey');

$viaPhone->sendSmsMessage('text', '+420213456789', 'Jozef Mak');
