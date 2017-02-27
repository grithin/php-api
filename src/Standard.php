<?
namespace Grithin\Api;

use \Grithin\Conform;

# may not be necessary
trait Standard{
	protected function response_maker($input){
		$conform = Conform::standard_instance($input);
		$rm = new ResponseMaker($conform);
		return $rm;
	}
}
