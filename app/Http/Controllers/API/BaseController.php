<?php 
namespace App\HTTP\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Response;
class BaseController extends Controller
{
    public function sendResponse($result, $message='')
    {
    	$response = [
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ];
        return Response::json($response, 200);
    }

    public function sendError($error, $errorMessages = [], $code = 404)
    {
    	$response = [
            'success' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }
        return Response::json($response, $code);
    }
}
?>