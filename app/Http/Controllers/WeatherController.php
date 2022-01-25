<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Controller;

const API_KEY_WEATHERBIT = "f5c1c329413e4939a7aab2c6d9b5ee4f";
const API_KEY_GOOGLE = "AIzaSyBGyhXGLBD6liQGETz__7dkbvc6zLqgEq8";

class WeatherController extends Controller
{
    public function getCovidData($city){
        $url = "https://covid19-japan-web-api.vercel.app/api/v1/prefectures";
        $resp = Http::get($url);
        $json = json_decode($resp);
        $ret = [];
        
        foreach ($json as $item) {
            if ($item->name_en == $city) {
                $ret["cases"] = $item->cases;
                $ret["deaths"] = $item->deaths;
                $ret["hospitalize"] = $item->hospitalize;
                $ret["severe"] = $item->severe;
                $ret["discharge"] = $item->discharge;
                $ret["symptom_confirming"] = $item->symptom_confirming;
                $ret["addr"] = $city." - ".$item->name_ja;
            }
        }
        return $ret;
    }
    
    public function getData(Request $request)
    {
        $days = 3;
        $postal_code = strval($request->input('postalcode')).",JP";            

        $ret = [];
        $ret["status"] = "OK";

        // get location based on postal code
        $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$postal_code}".
        "&key=".API_KEY_GOOGLE;
        $resp = Http::get($url);
        $data = json_decode($resp, true);

        if($data["status"] == "ZERO_RESULTS"){
            $ret["status"] = "ZERO_RESULTS";
        }else{
            $index = 1;
            $full_addr = "";
            $city_name = "";

            // loop and get address data
            while (true){
                if(in_array("administrative_area_level_1", $data["results"][0]["address_components"][$index]["types"])){
                    $city_name = $data["results"][0]["address_components"][$index]["long_name"];
                    $full_addr = $city_name . $full_addr;
                    break;
                }else{
                    $full_addr = ", ". $data["results"][0]["address_components"][$index]["long_name"] . $full_addr;
                }

                $index++;
            }
            
            $ret["fulladdr"] = $full_addr;
            $ret["lat"] = $data["results"][0]["geometry"]["location"]["lat"];
            $ret["lng"] = $data["results"][0]["geometry"]["location"]["lng"];

            // get weather data based on lat, lng
            $url2 = "https://api.weatherbit.io/v2.0/forecast/daily?".
            "days=".$days.  
            "&lat=".$ret["lat"]. 
            "&lon=".$ret["lng"]. 
            "&key=".API_KEY_WEATHERBIT;

            $resp = Http::get($url2);
            $data = json_decode($resp, true);
            
            for ($i = 0; $i < $days; $i++) {
                $ret["weather"][$i]["min_temp"] = $data["data"][$i]["min_temp"];
                $ret["weather"][$i]["max_temp"] = $data["data"][$i]["max_temp"];
                $ret["weather"][$i]["date"] = $data["data"][$i]["valid_date"];
                $ret["weather"][$i]["weather_icon"] = $data["data"][$i]["weather"]["icon"];
                $ret["weather"][$i]["weather_desc"] = $data["data"][$i]["weather"]["description"];
            } 

            // get covid data based on city
            $ret["covid"] = $this->getCovidData($city_name);
        }
        return $ret;
    }
}
