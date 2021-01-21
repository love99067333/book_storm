<?php

namespace App\Http\Controllers\Api\v1;

use App\Customer;
use App\CustomerBalance;
use App\CustomerBook;
use App\Http\Controllers\Controller;
use App\PurchaseRecord;
use App\Store;
use App\StoreBalance;
use App\StoreBook;
use App\StoreOpenningTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use OpenSSLCertificate;

class ApiController extends Controller
{
    // 
    /*
    TODO
    
    Input Data Validate

    Varaible 

    Import Json Validate
    
    
    
    */
    public function test()
    {
        # code...
        return [
            'status' => 200,
            'message' => "api working!"
        ];
    }
    //接收store json 資料
    /*
    URI : api/v1/store
    Description : Import store json
    Method : POST
    Form-data : 
    {
        store : //copy all of store json text
    }

    */
    public function store(Request $request)
    {
        # code...

        $stores = json_decode($request->input('stores'), true);
        DB::transaction(function ()   use ($stores) {
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
                        $end_time =  strtotime(date('1970/01/02' . 'H:i:s', $end_time));
                    } else {
                        $end_time =  strtotime(date('1970/01/01' . 'H:i:s', $end_time));
                    }


                    $start_time = strtotime(date('1970/01/01' . ' H:i:s', $start_time));

                    foreach ($days as $day) {

                        StoreOpenningTime::create([
                            'store_id' => $store->id,
                            'day' => array_search($day, $week, true) + 1,
                            'start_time' => $start_time,
                            'end_time' =>  $end_time,
                        ]);
                    }
                }
            }
            // $test = Store::all();
            // return $test;
        });
        return ['status' => '200', 'message' => 'import ' . count($stores) . ' stores'];
    }
    //接收user json 資料
    /*
    URI : api/v1/user
    Description : Import user json
    Method : POST
    Form-data : 
    {
        user : //copy all of user json text
    }

    */
    public function user(Request $request)
    {
        # code...

        $users = json_decode($request->input('users'), true);
        DB::transaction(function ()   use ($users) {
            foreach ($users as $user_data) {
                $user = Customer::firstOrCreate(['name' => $user_data['name']]);
                $user_balace = CustomerBalance::updateOrCreate(
                    ['customer_id' => $user->id],
                    ['balance' => $user_data['cashBalance']]
                );

                foreach ($user_data['purchaseHistory'] as $purchase) {
                    $store = Store::firstOrCreate(['name' => $purchase['storeName']]);

                    $customer_book = CustomerBook::create([
                        'customer_id' => $user->id,
                        'store_id' => $store->id,
                        'name' => $purchase['bookName'],
                    ]);
                    $record = PurchaseRecord::create([
                        'customer_id' => $user->id,
                        'store_id' => $store->id,
                        'customer_book_id' => $customer_book->id,
                        'amount' => $purchase['transactionAmount'],
                        'transactionDate' => date('Y-m-d H:i:s', strtotime($purchase['transactionDate'])),
                    ]);
                }
            }
        });

        return ['status' => '200', 'message' => 'import ' . count($users) . ' users'];
    }


    /*
    URI : api/v1/checkopenningstores
    Description : List all book stores that are open at a certain datetime
    Method : POST
    Form-data : 
    {
        datetime: 2021-01-14 03:20:20(e.g.)
    }

    */
    public function checkopenningstores(Request $request)
    {
        // 2021-02-05 12:55:77
        // return  strtotime('1970/1/1 0:0:0');
        $datetime = strtotime($request->input('datetime'));
        $day = date('N', $datetime);
        // return $day;
        $time = strtotime('1970/01/01 ' . date("H:i:s", $datetime));
        // return $time+86400;
        $week = ['Mon', 'Tues', 'Wed', 'Thurs', 'Fri', 'Sat', 'Sun'];
        $storedata = []; //回傳陣列
        // return $day . ' ' . date("H:i:s", $time); // test time

        $yestday = StoreOpenningTime::where('day', $day - 1)
            ->where('end_time', ">", $time + 86400);
        // ->where('end_time', ">", $time + 86400)
        // ->get();
        // return $yestday;
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
        // return $stores;
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
    Method : GET
    Form-data : 
    {
        time : 22:45:45
        detail : 1 //show openning day and time(0 = no 1 = show)
    }
    */
    public function checkopenningdaystores(Request $request)
    {
        # code...

        // return strtotime('0-0-0' . date('H:i:s', time()));
        $time = strtotime($request->input('time'));
        $detail = $request->input('detail');

        // return $time; //1611153933
        $time = strtotime(date('1970/01/01 ' . "H:i:s", $time));

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
            ->union($yestday);
        // ->groupby('store_id')
        // ->where(['start_time', "<", $time],['end_time', ">", $time])
        // ->get();
        if ($detail == '0') {
            $stores = $stores->groupby('store_id');
        }
        $stores = $stores->get();
        if ($stores) {
            foreach ($stores as $store) {
                if ($detail == '0') {
                    $s = [
                        'Store Name' => Store::find($store->store_id)->name,
                        // 'Open Day' => $week[$store->day - 1],
                    ]; //data format;
                }
                else if($detail =='1'){
                    $s = [
                        'Store Name' => Store::find($store->store_id)->name,
                        'Open Day' => $week[$store->day - 1],
                        'Open Time' => date("H:i:s", $store->start_time),
                        'End Time' => date("H:i:s", $store->end_time)
                    ]; //data format;
                }




                $storedata[] = $s;
            }
        }



        return ['status' => 200, 'Open Stores' => $storedata];
    }

    // openhours
    /*
    URI : api/v1/openhours
    Description : List all book stores that are open on a day of the week, at a certain time
    Method : GET
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
        // return   $sec;


        $storedata = []; //回傳陣列


        // return 'week '. $operator .  ' ?';
        // $query = StoreOpenningTime::select(DB::raw('store_id'),DB::raw(' (end_time - start_time) as sec'))


        // ->havingRaw('sumofweek' .'>'. '?', [$sec])->get();
        if ($time == 'week') {

            $raw = 'week';
        } else if ($operator == '>') {

            $raw = 'Maxday';
        } else if ($operator == '<') {

            $raw = 'Minday';
        }

        $query = StoreOpenningTime::select('store_id', DB::raw('SUM(end_time - start_time) as week'), DB::raw('Max(end_time - start_time) as Maxday'), DB::raw('MIN(end_time - start_time) as Minday'))
            ->havingRaw($raw . ' ' . $operator .  ' ?', [intval($sec)])
            ->groupBy('store_id')
            // ->havingRaw('week < ?', [250000])
            
            ->get();
        // return $query;
        // $allstores = StoreOpenningTime::select('store_id', DB::raw('SUM(end_time - start_time) as week'), DB::raw('Max(end_time - start_time) as Maxday'), DB::raw('MIN(end_time - start_time) as Minday'))
        // ->groupBy('store_id')
        // ->get();
        foreach ($query as $q) {
            $s = [
                'Store Id' => $q->store_id,
                'Store Name' => Store::find($q->store_id)->name,
                'Open hours per week' => date("d", $q->week) - 1 . '天'  . date("H小時i分s秒", $q->week-28800),
                'Max hour(one day in week)' => date("H小時i分s秒", $q->Maxday-28800),
                'Min hour(one day in week)' => date("H小時i分s秒", $q->Minday-28800)
                // 'Open hours per week' => intval($q->week/86400) .  '天' . intval($q->week/86400)
                // 'Max hour(one day in week)' => date("d天 H小時i分s秒", $q->Maxday),
                // 'Min hour(one day in week)' => date("d天 H小時i分s秒", $q->Minday)

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
    Method : GET
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
    Method : GET
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

    //booksamountinprice
    /*
    URI : api/v1/booksamountinprice
    Description : List all book stores that have more or less than x number of books within a price range
    Method : GET
    Form-data : 
    {
        maxprice:500,
        minprice:10,
        operator : more(or less),
        amount : 10,
    }
    */
    public function booksamountinprice(Request $request)
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
        $max_price = $request->input('maxprice');
        $min_price = $request->input('minprice');
        $amount = $request->input('amount');
        $storedata = []; //回傳陣列

        // where('balance', '<', $max_price)
        //     ->where('balance', '>', $min_price)
        $query = StoreBook::where('balance', '<', $max_price)
            ->where('balance', '>', $min_price)
            ->select('*', DB::raw('COUNT(store_id) as amount'))
            ->groupBy('store_id')
            ->havingRaw('amount ' . $operator . ' ?', [intval($amount)])
            ->get();

        foreach ($query as $q) {
            $s = [
                'Store Name' => Store::find($q->store_id)->name,
                'Books Amount' => $q->amount,
            ];
            $storedata[] = $s;
        }
        return ['status' => '200', 'stores' => $storedata];;
        // return $query;
    }
    //search
    /*
    URI : api/v1/search
    Description : Search for book stores or books by name, ranked by relevance to search term
    Method : GET
    Form-data : 
    {
        type : book(or store)
        name : Elixir
    }
    */
    public function search(Request $request)
    {


        $type = $request->input('type');

        $name = $request->input('name');

        $table = "";
        $data = []; //回傳陣列


        switch ($type) {
            case 'book':
                # code...
                $table = 'store_books';
                break;
            case 'store':
                # code...
                $table = 'stores';
                break;
            default:
                return [
                    'status' => 403,
                    'message' => "undefined type"
                ];
                break;
        }

        $query = DB::table($table)->where('name', '=', $name)
            ->orwhere('name', 'like', '%' . $name . '%')
            ->get();

        switch ($type) {
            case 'book':
                foreach ($query as $q) {
                    $b = [
                        'Store Name' => Store::find($q->store_id)->name,
                        'Book Name'  => $q->name,
                    ];

                    $data[] = $b;
                }



                break;
            case 'store':
                foreach ($query as $q) {
                    $s = [
                        'Store Name' => $q->name,
                    ];
                    $data[] = $s;
                }


                break;
            default:
        }

        return ['status' => '200', 'result' => $data];;
    }

    /* User */
    //search
    /*
    URI : api/v1/toptransaction
    Description : The top x users by total transaction amount within a date range
    Method : GET
    Form-data : 
    {
        startDate: 2020-01-01,
        endDate: 2021-01-01
        userAmount : 5 // show top 5 users
    }
    */
    public function toptransaction(Request $request)
    {
        // return date(strtotime('02/10/2020 04:04 AM');



        $start_date = $request->input('startDate');


        $end_date = $request->input('endDate');
        $userAmount = $request->input('userAmount');
        $user = []; //回傳陣列

        $query = PurchaseRecord::whereBetween('transactionDate', [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])
            ->select('*', DB::raw('SUM(amount) as sum'))
            ->groupBy('customer_id')
            ->orderBy('sum', 'desc')
            ->limit($userAmount)
            ->get();

        foreach ($query as $q) {
            $u = [
                'User Name' => Customer::find($q->customer_id)->name,
                'Total Amount' => $q->sum,
            ];
            $user[] = $u;
        }

        return [
            'status' => 200,
            'users' => $user,
        ];
    }


    //transactiontraffic
    /*
    URI : api/v1/transactiontraffic
    Description : The total number and dollar value of transactions that happened within a date range
    Method : GET
    Form-data : 
    {
        startDate: 2020-01-01,
        endDate: 2021-01-01
    }
    */
    public function transactiontraffic(Request $request)
    {
        // $start_date = $request->input('startDate');
        // $end_date = $request->input('endDate');

        $start_date = $request->input('startDate');
        $end_date = $request->input('endDate');


        $query = PurchaseRecord::whereBetween('transactionDate', [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])
            ->select(DB::raw('SUM(amount) as sum'), DB::raw('COUNT(id) as number'))

            ->first();
        return [
            'status' => 200,
            'number' => $query->number,
            'dollar' => $query->sum
        ];
    }
    //editdata
    /*
    URI : api/v1/editdata
    Description : Edit book store name, book name, book price and user name
    Method : PATCH
    Form-data : 
    {
        type : 'storename',
        id : 5 ,
        data : 'Test'
    }
    */
    public function editdata(Request $request)
    {

        $type = $request->input('type');
        $id = $request->input('id');
        $data = $request->input('data');

        $query = ""; //

        switch ($type) {
            case 'storename':
                $query = Store::find($id);
                $query->name = $data;
                $query->save();
                break;
            case 'bookname':
                $query = StoreBook::find($id);
                $query->name = $data;
                $query->save();
                break;
            case 'bookpirce':
                $query = StoreBook::find($id);
                $query->balance = $data;
                $query->save();
                break;
            case 'username':
                $query = Customer::find($id);
                $query->name = $data;
                $query->save();
                break;


            default:
                return [
                    'status' => 403,
                    'message' => 'undefined type',

                ];
                # code...
                break;
        }





        return [
            'status' => 200,
            'warning' => 'This api should declare something like store name if unique、input(old data) to edit、whose bookname(store or user => here i choose bookname of store) so i try my best to make it soft',
            'result' => $query
        ];
    }
    //topstore
    /*
    URI : api/v1/topstore
    Description : The most popular book stores by transaction volume, either by number of transactions or transaction dollar value
    Method : GET
    Form-data : 
    {
        None
    }
    */
    public function topstore()
    {
        $query = PurchaseRecord::select('store_id', DB::raw('COUNT(id) as amount'))
            ->groupBy('store_id')
            ->orderBy('amount', 'desc')
            ->first();

        return [
            'status' => 200,
            'Store Name' => Store::find($query->store_id)->name,
            'Number of Transactions' => $query->amount

        ];
    }
    //transactioninrange
    /*
    URI : api/v1/transactioninrange
    Description : Total number of users who made transactions above or below $v within a date range
    Method : GET
    Form-data : 
    {
        startDate:
        endDate:
        operator:
        v:
    }
    */
    public function transactioninrange(Request $request)
    {
        $start_date = $request->input('startDate');
        $end_date = $request->input('endDate');
        $operator = $request->input('operator');        
        $v = $request->input('v');
        // return $v;
        $user = []; //回傳陣列
        if ($operator == "more")
            $operator = '>';
        else if ($operator == "less")
            $operator = '<';
        else {
            return [
                'status' => 403,
                'message' => 'operator error'
            ];
        }
        // return $operator;
        // return $v;
        $query = PurchaseRecord::whereBetween('transactionDate', [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])
            ->select('*', DB::raw('COUNT(customer_id) as number'))
            ->groupBy('customer_id')
            ->havingRaw('number ' . $operator . ' ?', [intval($v)])
            ->get();
            // ->count();
        // return $query;
            // ->get();
        foreach ($query as $q) {
            $u = [
                'User Name' => Customer::find($q->customer_id)->name,
                'Number of transaction(s)' => $q->number,
            ];
            $user[] = $u;
        }
        return [
            'status' => 200,
            'users' => $user

        ];
    }

    //purchasing
    /*
    URI : api/v1/purchasing
    Description : Total number of users who made transactions above or below $v within a date range
    Method : GET
    Form-data : 
    {
        userid: 1 
        storeid: 1 
        bookid: 1 
    */
    public function purchasing(Request $request)
    {
        /*
        validate data
        Trasition:
            customer_balance - book_balance
            add record
            customer add new book

        
        */
        // Store::findOrFail(500);
        $store_id = $request->input('storeid');
        $customer_id = $request->input('userid');
        $book_id = $request->input('bookid');

        $store = Store::find($store_id);
        if (!$store) {
            return [
                'status' => 403,
                'message' => 'store not found'
            ];
        }
        $customer = Customer::find($customer_id);
        if (!$customer) {
            return [
                'status' => 403,
                'message' => 'customer not found'
            ];
        }
        $book = StoreBook::find($book_id);
        if (!$book) {
            return [
                'status' => 403,
                'message' => 'book not found'
            ];
        }
        if($book->store_id != $store_id){
            return [
                'status' => 403,
                'message' => 'book not exist in this store'
            ];
        }
        $customer_balance = CustomerBalance::where('customer_id', $customer->id)->first();
        if ($customer_balance->balance < $book->balance) {
            return [
                'status' => 403,
                'message' => 'Insufficient balance'
            ];
        }


        // $store_balance = StoreBalance::where('store_id',$store->id)->first()->increment('balance',$book->balance);
        // return date('Y-m-d H:i:s', strtotime(now()));

        //if any error in DB::transaction then rollback all
        try {
            //code...
            $purchase_record = DB::transaction(function ()   use ($store, $customer, $book, $customer_balance) {
                $store_balance = StoreBalance::where('store_id', $store->id)->first()->increment('balance', $book->balance);
                $customer_balance = CustomerBalance::where('customer_id', $customer->id)->decrement('balance', $book->balance);
                $customer_book = CustomerBook::create([
                    'customer_id' => $customer->id,
                    'store_id' => $store->id,
                    'name' => $book->name
                ]);
                return PurchaseRecord::create([
                    'customer_id' => $customer->id,
                    'store_id' => $store->id,
                    'customer_book_id' => $customer_book->id,
                    'amount' => $book->balance,
                    'transactionDate' =>  date('Y-m-d H:i:s', strtotime(now()))

                ]);
                // $store_balance 
            });
        } catch (\Throwable $th) {
            //throw $th;
            return [
                'status' => 403,
                'message' => "trasation failed"
            ];
        }

        $customer_balance = CustomerBalance::where('customer_id', $customer->id)->first();
        $store_balance = StoreBalance::where('store_id', $store->id)->first();
        return [
            'status' => 200,
            'user new cash balace' => $customer_balance->balance,
            'store new cash blace' => $store_balance->balance,
            'transation id' => $purchase_record->id
        ];
    }

    /*
    URI : api/v1/claendb
    Description : Clean all db data
    Method : get
    Form-data : 
    {
        password : kdan
    }

    */
    public function cleandb(Request $request){ 

        if($request->input('password') != 'kdan'){
            return [
                'status' => '403',
                'message' => 'permission denied'
            ];
        };
        Artisan::call('migrate:refresh');
        return [
            
            'status' => '200',
            'message' => 'db cleaned, please call import user、store json api again.'
        ];



    }


}
