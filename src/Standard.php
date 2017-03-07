<?
namespace Grithin\Api;

trait Standard{
	public $request; # request object with `input` and `method` attributes
	public $conform; # shared conform instance
	public $response_maker; # chared ResponseMaker instance
}
