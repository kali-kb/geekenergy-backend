<?php

namespace App\Http\Controllers;

use Symfony\Component\Console\Output\Output;
use App\Models\User;
use App\Models\Challenge;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Models\Solved;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class MiscController extends Controller
{
    public function __construct(){
        $this->endpoint = env("CODE_PROCESSING_SERVER_ENDPOINT");
        $this->api_key = env("API_KEY");
        $this->lang_ids = ["javascript" => 93, "python3" => 71];
    }


    public function executor(Request $request){

        try{

            $data = json_decode($request->getContent());
      
            $payload = [
                'source_code' => $data->script,
                'language_id' => $this->lang_ids[$data->language]
            ];
            $headers = ["X-RapidAPI-Key" => $this->api_key, "Content-Type" => "application/json"];

            $output = Http::withHeaders($headers)->post($this->endpoint .'/submissions?wait=true', $payload);
            // error_log("" . $response->json());
            error_log("output: " . $output);
            return $output;
        }
        catch(Exception $exception){
            error_log("something went wrong" . $exception);
        }

    }

    public function runTest(Request $request){
        $data = json_decode($request->getContent());
        $challenge = Challenge::where("challenge_id", $data->challenge)->get()->first();
        $user = User::where("user_id", $data->user)->get()->first();
        error_log('' . json_encode($data));
        error_log('script: '. $data->script);
        $payload = [
          "source_code" => $data->script,
          "language_id" => $this->lang_ids[$data->language],
          "expected_output" => $challenge->example_output
        ];
        $headers = ["X-RapidAPI-Key" => $this->api_key, "Content-Type" => "application/json"];
        $point = ["Easy" => 25, "Medium" => 50 ,"Hard" => 100];

        $response = Http::withHeaders($headers)->post($this->endpoint.'/submissions?wait=true', $payload);
        $parsed = $response->json();
        $output = str_replace(' ', '', $parsed['stdout']);
        // error_log("output: " . str_replace(' ', '', $parsed['stdout']) . "expected_output: " . $challenge->example_output);
        if (Str::remove("\n", str_replace(' ', '', $output)) == Str::remove(" ", $challenge->example_output)){
            error_log("correct");
            $won_point = $point[$challenge->difficulty];
            $user->points = $won_point;
            $user->problems_solved += 1;
            $user->save();

            Solved::create([
                "user_id" => $user->user_id,
                "challenge_id" => $challenge->challenge_id
            ]);

            Notification::create([
                "user_id" => $user->user_id,
                "notification_message" => "Congradulations " . $won_point . " points have been added to your profile"
            ]);
            return response()->json(["result" => "pass", "points_added" => $won_point, "code_output" => $output]);
        }
        else{
            error_log("incorrect");
            return response()->json(["result" => "fail", "points_added" => 0, "code_output" => $output]);
        }
    }



    public function getNotification(Request $request){
        $id = $request->query("user");
        $notifications = Notification::where("user_id", $id)->get();
        return response()->json(["notifications" => $notifications, "user" => Auth::user()]);
    }

    public function guideList(Request $request){
        $guides = DB::table("guide")->get();
        return response()->json(["guides" => $guides]);
    }

    public function guide(Request $request, $id){
        $guide = DB::table("guide")->where("id", $id)->get()->first();
        return response()->json($guide);
    }

}
