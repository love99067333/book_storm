<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Store;
use App\StoreBalance;
use App\StoreBook;
use App\StoreOpenningTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenSSLCertificate;

class ApiController extends Controller
{
    //
    public function test()
    {
        # code...
        return [
            'status' => 204,
            'message' => "no person"
        ];
    }
    //接收store json 資料
    public function store(Request $request)
    {
        # code...

        // "cashBalance": 4483.84,
        // "books": [{
        //     "bookName": "Ruby: The Autobiography",
        //     "price": 13.88
        //   },
        //   {
        //     "bookName": "Ruby!",
        //     "price": 10.64
        //   },
        //   {
        //     "bookName": "Ruby, Ruby: A Murder Mystery",
        //     "price": 12.45
        //   },
        //   {
        //     "bookName": "Ruby: Unexpected Love... (ruby Trilogy)",
        //     "price": 10.59
        //   },
        //   {
        //     "bookName": "Where's Ruby? (max And Ruby)",
        //     "price": 13.5
        //   },
        //   {
        //     "bookName": "Ruby: Learn Ruby In 24 Hours Or Less - A Beginner's Guide To Learning Ruby Programming Now (ruby, Ruby Programming, Ruby Course)",
        //     "price": 13.5
        //   },
        //   {
        //     "bookName": "Refactoring: Ruby Edition: Ruby Edition (addison-wesley Professional Ruby Series)",
        //     "price": 12.56
        //   },
        //   {
        //     "bookName": "Mama Ruby (a Mama Ruby Novel)",
        //     "price": 12.38
        //   },
        //   {
        //     "bookName": "Ruby Red (the Ruby Red Trilogy)",
        //     "price": 11.64
        //   },
        //   {
        //     "bookName": "Metaprogramming Ruby 2: Program Like The Ruby Pros (facets Of Ruby)",
        //     "price": 10.51
        //   },
        //   {
        //     "bookName": "Ruby River",
        //     "price": 10.2
        //   },
        //   {
        //     "bookName": "Ruby Holler",
        //     "price": 14.0
        //   },
        //   {
        //     "bookName": "Ruby Phrasebook",
        //     "price": 11.79
        //   },
        //   {
        //     "bookName": "Sandy Ruby",
        //     "price": 10.15
        //   }
        // ],
        // "openingHours": "Mon, Fri 2:30 pm - 8 pm / Tues 11 am - 2 pm / Wed 1:15 pm - 3:15 am / Thurs 10 am - 3:15 am / Sat 5 am - 11:30 am / Sun 10:45 am - 5 pm",
        // "storeName": "Look Inna Book"

        DB::transaction(function ()   use ($request) {
            $stores = json_decode($request->input('stores'), true);
            foreach ($stores as $store_data) {
                $store = Store::firstOrCreate(['name' => $store_data['storeName']]);
                $store_balance = StoreBalance::updateOrCreate(
                    ['store_id' => $store->id],
                    ['balance' => $store_data['cashBalance']]
                );
                foreach ($store_data['books'] as $book) {
                    StoreBook::create([
                        'store_id' => $store->id,
                        'name' => $book['bookName'],
                        'balance' => $book['price']
                    ]);
                }
                $openningday_array = explode(" / ", $store_data['openingHours']);
                //openningday_array might be 
                // Mon, Fri 2:30 pm - 8 pm
                // Tues 11 am - 2 pm
                // Wed 1:15 pm - 3:15 am
                // ...

                $week = ['Mon', 'Tues', 'Wed', 'Thurs', 'Fri', 'Sat', 'Sun'];
                foreach ($openningday_array as $openningday) {
                    // Mon, Fri 2:30 pm - 8 pm
                    // or Tues - Wed 6:45 am - 3 pm  it might many days like Mon - Wed => Mon,Tues,Wed
                    if (explode(" ", $openningday)[1] == '-') {

                        $tmp = explode(" ", $openningday);
                        $days = [];
                        $flag = 0;
                        foreach ($week as $w) {
                            if ($flag == 1) {
                                $days[] = $w;
                            } else if ($w == $tmp[0]) {
                                $flag = 1;
                                $days[] = $w;
                            }

                            if ($w == $tmp[2]) {
                                $flag = 0;
                            }
                        }

                        array_splice($tmp, 0, 3);
                        $time_array = $tmp;
                    } else {
                        $days = explode(", ", $openningday);
                        /*if it has muti day might be 
                          [0] Mon
                          [1] Fri 2:30 pm - 8 pm
        
                        */
                        //take last one explode it by " " and get first item
                        $tmp = explode(" ", end($days));
                        /* 
                        tmp might be:
                        [0] Fri
                        [1] 2:30
                        [2] pm
                        [3] -
                        */

                        array_splice($days, count($days) - 1, 1);
                        $days[] =  $tmp[0];
                        /*
                        days might be :
                        [0] Mon
                        [1] Fri 
                        */
                        array_splice($tmp, 0, 1);
                        $time_array = $tmp;
                    }



                    $start_time = $time_array[0] . $time_array[1];
                    // if ($time_array[1] == 'pm') {
                    //     $start_time += 12;
                    // }
                    $end_time = $time_array[3] . $time_array[4];

                    // if ($time_array[4] == 'pm') {
                    //     $end_time += 12;
                    // }
                    $start_time = strtotime($start_time);
                    $end_time = strtotime($end_time);

                    if ($end_time < $start_time) {
                        $end_time += 86400;
                    }

                    foreach ($days as $day) {

                        StoreOpenningTime::create([
                            'store_id' => $store->id,
                            'day' => array_search($day, $week, true) + 1,
                            'start_time' => $start_time,
                            'end_time' => $end_time
                        ]);
                    }
                }
            }
            // $test = Store::all();
            // return $test;
        });
    }
    //接收user json 資料
    public function user(Request $request)
    {
        # code...
    }


    /*
    URI : api/v1/checkopenningstores
    Description : List all book stores that are open at a certain datetime
    Method : Post
    Form-data : 
    {
        datetime: 2021-01-14 03:20:20(e.g.)
    }

    */
    public function checkopenningstores(Request $request)
    {
        // 2021-02-05 12:55:77
        $datetime = strtotime($request->input('datetime'));
        $day = date('N', $datetime);
        $time = strtotime(date("H:i:s", $datetime));

        $week = ['Mon', 'Tues', 'Wed', 'Thurs', 'Fri', 'Sat', 'Sun'];
        $storedata = []; //回傳陣列
        // return $day . ' ' . date("H:i:s", $time); // test time

        $yestday = StoreOpenningTime::where('day', $day - 1)
            ->where('end_time', ">", $time + 86400);
        // ->where('end_time', ">", $time + 86400)
        // ->get();
        // if ($stores) {
        //     foreach ($stores as $store) {
        //         $s = [
        //             'Store Name' => Store::find($store->store_id)->name,
        //             'Open Day' => $week[$store->day-1] ,
        //             'Open Time' => date("H:i:s", $store->start_time) . ' ~ ' . date("H:i:s", $store->end_time)
        //         ]; //data format;
        //         $storedata[] = $s;
        //     }
        // }

        // unset($stores);
        $stores = StoreOpenningTime::where('day', $day)
            ->where('start_time', "<", $time)
            ->where('end_time', ">", $time)
            ->union($yestday)
            ->groupby('store_id')
            // ->where(['start_time', "<", $time],['end_time', ">", $time])
            ->get();
        // return $stores;
        // return $stores[0]->store_id;
        if ($stores) {
            foreach ($stores as $store) {
                $s = [
                    'Store Name' => Store::find($store->store_id)->name,
                    'Open Day' => $week[$store->day - 1],
                    'Open Time' => date("H:i:s", $store->start_time),
                    'End Time' => date("H:i:s", $store->end_time)
                ]; //data format;
                $storedata[] = $s;
            }
        }

        // $yestday  = DB::table('store_openning_time')
        // ->

        // return date("H:i:s", $stores[0]['start_time']);
        //groupBy('name')

        return ['status' => 200, 'Open Stores' => $storedata];
    }

    /*
    URI : api/v1/checkopenningdaystores
    Description : List all book stores that are open on a day of the week, at a certain time
    Method : Post
    Form-data : 
    {
        time : 22:45:45
    }
    不知道這個要得輸入是甚麼 time 還是 day
    */
    public function checkopenningdaystores(Request $request)
    {
        # code...
        $time = strtotime($request->input('time'));

        $storedata = []; //回傳陣列
        $yestday = StoreOpenningTime::where('end_time', ">", $time + 86400);

        $week = ['Mon', 'Tues', 'Wed', 'Thurs', 'Fri', 'Sat', 'Sun'];
        // ->where('end_time', ">", $time + 86400)
        // ->get();
        // if ($stores) {
        //     foreach ($stores as $store) {
        //         $s = [
        //             'Store Name' => Store::find($store->store_id)->name,
        //             'Open Day' => $week[$store->day-1] ,
        //             'Open Time' => date("H:i:s", $store->start_time) . ' ~ ' . date("H:i:s", $store->end_time)
        //         ]; //data format;
        //         $storedata[] = $s;
        //     }
        // }

        // unset($stores);
        $stores = StoreOpenningTime::where('start_time', "<", $time)
            ->where('end_time', ">", $time)
            ->union($yestday)
            // ->groupby('store_id')
            // ->where(['start_time', "<", $time],['end_time', ">", $time])
            ->get();
        if ($stores) {
            foreach ($stores as $store) {
                $s = [
                    'Store Name' => Store::find($store->store_id)->name,
                    'Open Day' => $week[$store->day - 1],
                    'Open Time' => date("H:i:s", $store->start_time),
                    'End Time' => date("H:i:s", $store->end_time)
                ]; //data format;
                $storedata[] = $s;
            }
        }

        // return ['status' => 404, 'message' => 'Not yet finished'];


        return ['status' => 200, 'Open Stores' => $storedata];
    }

    // openhours
    /*
    URI : api/v1/openhours
    Description : List all book stores that are open on a day of the week, at a certain time
    Method : Post
    Form-data : 
    {
        operator : more(or less),  
        time : day(or week),
        hours : 14,
    }
    不知道這個要得輸入是甚麼 time 還是 day
    */
    public function openhours(Request $request)
    {

        $operator = $request->input('operator');
        if ($operator == "more")
            $operator = '>';
        else if ($operator == 'less')
            $operator = '<';
        else {
            return [
                'status' => 403,
                'message' => 'operator error'
            ];
        }
        $time = $request->input('time');
        if ($time !== 'week' && $time !== 'day') {

            return [
                'status' => 403,
                'message' => 'time error'
            ];
        }
        $sec = intval($request->input('hours') * 3600);
        // return $sec;


        $storedata = []; //回傳陣列


        // return 'week '. $operator .  ' ?';
        // $query = StoreOpenningTime::select(DB::raw('store_id'),DB::raw(' (end_time - start_time) as sec'))


        // ->havingRaw('sumofweek' .'>'. '?', [$sec])->get();
        if ($time == 'week') {
            // $query = StoreOpenningTime::select('store_id', 'store_id', DB::raw('SUM(end_time - start_time) as week'), DB::raw('Max(end_time - start_time) as Maxday'), DB::raw('MIN(end_time - start_time) as Minday'))
            //     ->havingRaw('week ' . $operator .  ' ?', [intval($sec)])

            //     // ->havingRaw('week < ?', [250000])
            //     ->groupBy('store_id')
            //     ->get();
            $raw = 'week';
        } else if ($operator == '>') {
            // $query = StoreOpenningTime::select('store_id', 'store_id', DB::raw('SUM(end_time - start_time) as week'), DB::raw('Max(end_time - start_time) as Maxday'), DB::raw('MIN(end_time - start_time) as Minday'))
            //     ->havingRaw('Maxday > ?', [intval($sec)])

            //     // ->havingRaw('week < ?', [250000])
            //     ->groupBy('store_id')
            //     ->get();
            $raw = 'Maxday';
        } else if ($operator == '<') {
            // $query = StoreOpenningTime::select('store_id','store_id', DB::raw('SUM(end_time - start_time) as week'), DB::raw('Max(end_time - start_time) as Maxday'), DB::raw('MIN(end_time - start_time) as Minday'))
            //     ->havingRaw('Minday < ?', [intval($sec)])

            //     // ->havingRaw('week < ?', [250000])
            //     ->groupBy('store_id')
            //     ->get();
            $raw = 'Minday';
        }

        $query = StoreOpenningTime::select('store_id', 'store_id', DB::raw('SUM(end_time - start_time) as week'), DB::raw('Max(end_time - start_time) as Maxday'), DB::raw('MIN(end_time - start_time) as Minday'))
            ->havingRaw($raw . ' ' . $operator .  ' ?', [intval($sec)])

            // ->havingRaw('week < ?', [250000])
            ->groupBy('store_id')
            ->get();
        //應該可以一段結束

        // $allstores = StoreOpenningTime::select('store_id', DB::raw('SUM(end_time - start_time) as week'), DB::raw('Max(end_time - start_time) as Maxday'), DB::raw('MIN(end_time - start_time) as Minday'))
        // ->groupBy('store_id')
        // ->get();
        foreach ($query as $q) {
            $s = [
                'Store Name' => Store::find($q->store_id)->name,
                'Open hours per week' => date("d", $q->week) - 1 . '天'  . date("H小時i分s秒", $q->week),
                'Max hour(one day in week)' => date("H小時i分s秒", $q->Maxday),
                'Min hour(one day in week)' => date("H小時i分s秒", $q->Minday)

            ];
            $storedata[] = $s;
        }

        return ['status' => 200, 'Available Store(s)' => $storedata];

        // return $query;
        // return $query[0]; 
        // ->max('sec')
        // ->orderBy('store_id')
        // ->orderBy('sec')
        // ;
        return $query;
        // ->pluck('sec','store_id');
        $week = []; //store store_id => sec


        return $week;



        // return $OpenSec; 

    }


    /*
    URI : api/v1/booksprice
    Description : List all books that are within a price range, sorted by price or alphabetically
    Method : Post
    Form-data : 
    {
        maxprice:500,
        minprice:10,
    }
    */
    public function booksprice(Request $request)
    {

        $max_price = $request->input('maxprice');

        $min_price = $request->input('minprice');

        $bookdata = []; //回傳陣列
        // $books = StoreBook::all();
        // return $books;
        $books = StoreBook::where('balance', '<', $max_price)
            ->where('balance', '>', $min_price)
            ->orderBy('balance')
            ->orderBy('name')
            ->get();

        foreach ($books as $book) {
            $b = [
                'Store Name' => Store::find($book->store_id)->name,
                'Book Price' => $book->balance,
                'Book Name' => $book->name,
            ];
            $bookdata[] = $b;
        }

        return ['status' => 200, 'books' => $bookdata];
    }

    /*
    URI : api/v1/booksamount
    Description : List all book stores that have more or less than x number of books
    Method : Post
    Form-data : 
    {
        operator : more(or less),
        amount : 10,
    }
    */
    public function booksamount(Request $request)
    {
        $operator = $request->input('operator');
        if ($operator == "more")
            $operator = '>';
        else if ($operator == 'less')
            $operator = '<';
        else {
            return [
                'status' => 403,
                'message' => 'operator error'
            ];
        }
        $amount = $request->input('amount');


        $storedata = []; //回傳陣列

        $query = StoreBook::select('*', DB::raw('COUNT(store_id) as amount'))
            ->groupBy('store_id')
            ->havingRaw('amount ' . $operator . ' ?', [intval($amount)])
            ->get();
        foreach ($query as $q) {
            $b = [
                'Store Name' => Store::find($q->store_id)->name,
                'Book Amount' => $q->amount
            ];
            $storedata[] = $b;
        }

        return ['status' => '200', 'stores' => $storedata];
    }
}
