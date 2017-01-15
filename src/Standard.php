<?
namespace Grithin\Api;

use \Grithin\Conform;

trait Standard{
	protected function response_maker($input){
		$conform = Conform::standard_instance($input);
		$rm = new ResponseMaker($conform);
		return $rm;
	}
}
