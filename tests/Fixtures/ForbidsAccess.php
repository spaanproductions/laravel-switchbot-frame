<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A stand-in for a host's authorization middleware — always forbids.
 */
class ForbidsAccess
{
	public function handle(Request $request, Closure $next): Response
	{
		abort(403);
	}
}
