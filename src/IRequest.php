<?php

declare(strict_types=1);

namespace ADT\ViaPhone;

interface IRequest
{
	public const
		GET = 'GET',
		POST = 'POST',
		HEAD = 'HEAD',
		PUT = 'PUT',
		DELETE = 'DELETE',
		PATCH = 'PATCH',
		OPTIONS = 'OPTIONS';
}
