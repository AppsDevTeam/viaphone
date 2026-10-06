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
```php
$viaPhone = new \ADT\ViaPhone\ViaPhone('apiKey');
$viaPhone->sendSmsMessage('text', '+420213456789');

// Připomínka termínu - ViaPhone vyhodnotí odpověď pacienta a pošle webhook
// s polem `confirmation` (1 přijde, 0 nepřijde, -1 nejde poznat)
$viaPhone->sendSmsMessage('Připomínáme termín zítra v 10:00.', '+420213456789', confirmationRequested: true);
```
