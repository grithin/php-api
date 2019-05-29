With an API, there are layers of concern
-	avenue of communication (ex HTTP)
-	mode of communication (ex JSON)
-	layers of authentication (can access api, particular user can use particular method)
-	structure of request
-	structure of response

This repository is primarily concerned with the structure of the request and response - particularly in:
-	standardizing input to method and method input
-	mapping a request to some class methods
-	standardizing success and error responses
-	standardizing how errors are included in responses

# ResponseMaker
ResponseMaker handles logic involved in creating a standard response structure.  It integrates with Conform to apply validation rules and generate standard-strutured errors if any are present.  An example long form response structure:
```json
{
	"status":"fail",
	"errors":[
		{"fields":["name"],"message":"v.filled","rule":{"flags":{"break":true},"params":[],"fn_path":"v.filled"},"type":"v.filled","params":[]}
	],
	"data":null
}
```
`status` is either `fail` or `success`.  In standard form, `errors` is always an array, and `data` is always present.  In minimized standard form, if `errors` is an empty array, it is excluded from the response, and if `data` is null, it is excluded from the response.

# Notes
A `status` can be `success` even with errors.  This, however, requires the `status` is set to `success` prior to `ResponseMaker::result()`


# Input
## Api Method

There are three variables containing the input (not references)
1.	`method($input)` : the first parameter to the api method
2.	`$api_instance->request->input`
3.	`$api_instance->conform->input`

Inputs #1 and #2 will be the same at the start of an API method.  In the event #1 is manipulated in the method, #2 serves as a back up for the original.
It is expectable that #3 will change in accordance to what is desired for Conform to validate.