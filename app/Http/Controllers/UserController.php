<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use Auth;
use DB;

use App\Models\User;
use App\Models\Button;
use App\Models\Link;


class UserController extends Controller
{

    //Statistics of the number of clicks and links 
    public function index()
    {
        $userId = Auth::user()->id;

        $littlelink_name = Auth::user()->littlelink_name;

        $links = Link::where('user_id', $userId)->select('link')->count();

        $clicks = Link::where('user_id', $userId)->sum('click_number');

        return view('studio/index', ['littlelink_name' => $littlelink_name, 'links' => $links, 'clicks' => $clicks]);
    }

    //Show littlelink page. example => http://127.0.0.1:8000/+admin
    public function littlelink(request $request)
    {
        $littlelink_name = $request->littlelink;
        $id = User::select('id')->where('littlelink_name', $littlelink_name)->value('id');

        if (empty($id)) {
            return abort(404);
        }
        
        $userinfo = User::select('name', 'littlelink_name', 'littlelink_description')->where('id', $id)->first();
        $information = User::select('name', 'littlelink_name', 'littlelink_description')->where('id', $id)->get();
        
        $links = DB::table('links')->join('buttons', 'buttons.id', '=', 'links.button_id')->select('links.link', 'links.id', 'links.button_id', 'links.title', 'buttons.name')->where('user_id', $id)->orderBy('up_link', 'asc')->orderBy('order', 'asc')->get();

        return view('littlelink', ['userinfo' => $userinfo, 'information' => $information, 'links' => $links, 'littlelink_name' => $littlelink_name]);
    }

    //Show buttons for add link
    public function showButtons()
    {
        $data['buttons'] = Button::select('name')->get();
        return view('studio/add-link', $data);
    }

    //Save add link
    public function addLink(request $request)
    {
        $request->validate([
            'link' => 'required',
            'title' => 'required',
            'button' => 'required'
        ]);

        $link = $request->link;
        $title = $request->title;
        $button = $request->button;

        $userId = Auth::user()->id;
        $buttonId = Button::select('id')->where('name' , $button)->value('id');

        $links = new Link;
        $links->link = $link;
        $links->user_id = $userId;
        $links->title = $title;
        $links->button_id = $buttonId;
        $links->save();

        return back();
    }

    //Count the number of clicks and redirect to link
    public function clickNumber(request $request)
    {
        $link = $request->link;
        $query = $request->query();
        $linkId = $request->id;

        if(empty($link && $linkId))
        {
            return abort(404);
        }
        
        if(!empty($query)) {
        	$qs = [];
        	foreach($query as $qk => $qv) { $qs[] = $qk .'='. $qv; }
        	$link = $link .'?'. implode('&', $qs);
        }

        Link::where('id', $linkId)->increment('click_number', 1);

        return redirect()->away($link);
    }
    
    //Show link, click number, up link in links page
    public function showLinks()
    {
        $userId = Auth::user()->id;
        
        $data['links'] = Link::select('id', 'link', 'title', 'order', 'click_number', 'up_link')->where('user_id', $userId)->orderBy('up_link', 'asc')->orderBy('order', 'asc')->paginate(10);
        return view('studio/links', $data);
    }

    //Delete link
    public function deleteLink(request $request)
    {
        $linkId = $request->id;

        Link::where('id', $linkId)->delete();
        
        return back();
    }

    //Raise link on the littlelink page
    public function upLink(request $request)
    {
        $linkId = $request->id;
        $upLink = $request->up;

        if($upLink == 'yes'){
            $up = 'no';
        }elseif($upLink == 'no'){
            $up = 'yes';
        }

        Link::where('id', $linkId)->update(['up_link' => $up]);

        return back();
    }

    //Show link to edit
    public function showLink(request $request)
    {
        $linkId = $request->id;

        $link = Link::where('id', $linkId)->value('link');
        $title = Link::where('id', $linkId)->value('title');
        $order = Link::where('id', $linkId)->value('order');
        $buttonId = Link::where('id', $linkId)->value('button_id');

        $buttons = Button::select('id', 'name')->get();
       
        return view('studio/edit-link', ['buttonId' => $buttonId, 'buttons' => $buttons, 'link' => $link, 'title' => $title, 'order' => $order, 'id' => $linkId]);

    }

    //Save edit link
    public function editLink(request $request)
    {
        $request->validate([
            'link' => 'required',
            'title' => 'required',
            'button' => 'required',
        ]);

        $link = $request->link;
        $title = $request->title;
        $order = $request->order;
        $button = $request->button;
        $linkId = $request->id;

        $buttonId = Button::select('id')->where('name' , $button)->value('id');

        Link::where('id', $linkId)->update(['link' => $link, 'title' => $title, 'order' => $order, 'button_id' => $buttonId]);

        return redirect('/studio/links');
    }

    //Show littlelinke page for edit
    public function showPage(request $request)
    {
        $userId = Auth::user()->id;

        $data['pages'] = User::where('id', $userId)->select('littlelink_name', 'littlelink_description')->get();

        return view('/studio/page', $data);
    }

    //Save littlelink page (name, description, logo)
    public function editPage(request $request)
    {
        $userId = Auth::user()->id;
        $littlelink_name = Auth::user()->littlelink_name;

        $profilePhoto = $request->file('image');
        $pageName = $request->pageName;
        $pageDescription = $request->pageDescription;
        
        User::where('id', $userId)->update(['littlelink_name' => $pageName, 'littlelink_description' => $pageDescription]);

        if(!empty($profilePhoto)){
        $profilePhoto->move(public_path('/img'), $littlelink_name . ".png");
        }

        return back();
    }

    //Show user (name, email, password)
    public function showProfile()
    {
        $userId = Auth::user()->id;

        $data['profile'] = User::where('id', $userId)->select('name', 'email', 'role')->get();

        return view('/studio/profile', $data);
    }

    //Save user (name, email, password)
    public function editProfile(request $request)
    {
        $request->validate([
            'name' => 'required|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
        ]);

        $userId = Auth::user()->id;

        $name = $request->name;
        $email = $request->email;
        $password = Hash::make($request->password);

        User::where('id', $userId)->update(['name' => $name, 'email' => $email, 'password' => $password]);

        return back();
    }
}
