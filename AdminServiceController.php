<?php
/**
 * @author EVNE Developers http://evnedev.com/
 */

namespace App\Http\Controllers\Admin;

use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\AdminBaseController;

use App\Models\Service;
use App\Models\Languages;
use App\Models\Type;
use App\Models\Option;
use App\Models\OptionVariant;


class AdminServiceController extends AdminBaseController
{


	public function typePakages(){
		$title = "Packages and services";

		return view('admin.package-service.showPakageType', compact(['title']));
	}

	// Show all services
    public function getIndex(){

		$title = 'Services control panel';

    	$services = Service::Locale()->addSelect('ev_service.*')->addSelect('name','description')
    				->with(['packages'=>function($query){
    					$query->Locale()->addSelect('ev_packages.id','name');
    				}])
    				->get();

		return view('admin.service.showService', compact(['title', 'services']));
    }


    // all request which
	public function postIndex( Request $request ) {
	
		// All id service for deletion
		$ids = $request->get('check');

		// if action is delete
		if( $request->get('action') == 'delete' ) {
			// delete from BD
			Service::destroy( $ids );
		}

		return redirect()->back()->with('success','Successfully deleted');
	}


	// add new service
	public function getAdd( Request $request ){

		$title = 'Add new Service';

		$langs = Languages::all();
		
		$post = new Service;

		$types = Type::MinSelect()
			->with(['options'=>function($query){
				$query->where('use_in_service',1)->orderBy('lft')->Locale()->addSelect('name')->addSelect('ev_options.*');
				$query->with(['variants'=>function($query_1){
						$query_1->orderBy('lft')->Locale()->addSelect('name')->addSelect('ev_option_variants.*');
					}]);
			}])
			->get();

		return view('admin.service.editService', compact('title','post','langs','types') );
	}


	// create new option
	public function postAdd(Request $request){

		// select all Languages 
		$langs = Languages::all();

		$service = Service::create();

		foreach($langs as $lang){
			$info =array_merge($request->{$lang->name},['lang_id' => $lang->id]);
			$service->localizations()->create( $info );
		}

		// sorting not allowed option variants by type
		$type_ids = $request->get('type_ids');

		$option_variants_ids = $request->get('option_variants');

		$option_variants = OptionVariant::whereIn('id',$option_variants_ids)->get();

		$options = Option::whereIn('id',$option_variants->lists('option_id')->toArray())->get();

		$allowed_option_ids = $options->filter(function ($value) use ($type_ids){
							return in_array($value->type_id,$type_ids);
						})->lists('id')->toArray();

		$option_variants_ids = $option_variants->filter(function ($value) use ($allowed_option_ids){
							return in_array($value->option_id,$allowed_option_ids);
						})->lists('id')->toArray();

		// attaching to service
		$service->types()->attach($type_ids);

		// attaching to option_variants
		$service->option_variants()->attach($option_variants_ids);

		if ( $request->has('iframe') ){
			\Session::flash('success', 'Service successfully created!');
			return view('admin.iframe');
		}

		return redirect('/master/service/edit/'.$service->id)->with('success', 'Service successfully created!');
	}



	// service editing
	public function getEdit($id){

		$post = Service::with('types')->with('option_variants')->with('localizations')->findOrFail($id);

		$title = 'Editing service';

		$langs = Languages::all();

		$types = Type::MinSelect()
			->with(['options'=>function($query){
				$query->where('use_in_service',1)->orderBy('lft')->Locale()->addSelect('name')->addSelect('ev_options.*');
				$query->with(['variants'=>function($query_1){
						$query_1->orderBy('lft')->Locale()->addSelect('name')->addSelect('ev_option_variants.*');
					}]);
			}])
			->get();

		return view('admin.service.editService', compact(['title','post','langs','types']));
    }
    

 	// create or upadate service, we pass the identifier( $id ) of a specific service, update it and the localization table
	public function postEdit( Request $request, $id ){

		// select all Languages 
		$langs = Languages::all();

		$service = Service::findOrFail($id);

		foreach($langs as $lang){
			$info =array_merge($request->{$lang->name},['lang_id' => $lang->id]);
			$service->localizations()->updateOrCreate(['lang_id' => $lang->id], $info );
		}

		// sorting not allowed option variants by type
		$type_ids = $request->get('type_ids');
		if ( !$type_ids || !is_array($type_ids) || !count($type_ids) )
			$type_ids = [];

		$option_variants_ids = $request->get('option_variants');

		$option_variants = OptionVariant::whereIn('id',$option_variants_ids)->get();

		$options = Option::whereIn('id',$option_variants->lists('option_id')->toArray())->get();

		$allowed_option_ids = $options->filter(function ($value) use ($type_ids){
							return in_array($value->type_id,$type_ids);
						})->lists('id')->toArray();

		$option_variants_ids = $option_variants->filter(function ($value) use ($allowed_option_ids){
							return in_array($value->option_id,$allowed_option_ids);
						})->lists('id')->toArray();

		// attaching to service
		$service->types()->detach();
		$service->types()->attach($type_ids);

		// attaching to option_variants
		$service->option_variants()->detach();
		$service->option_variants()->attach($option_variants_ids);
		foreach ($option_variants_ids as $key => $option_variants_id) {
			$option_id = $option_variants->where('id',$option_variants_id)->first()->option_id;
			$option_type_id = $options->where('id',$option_id)->first()->type_id;
			$service->option_variants()->updateExistingPivot($option_variants_id,['option_id'=>$option_id,'type_id'=>$option_type_id]);
		}

		return redirect('/master/service/edit/'.$id)->with('success', 'Service successfully updated!');
	}


	// return last added Service = [item->id => item->name ]
	public function postLastAdded(Request $request){

		$result = 0;
		$item = Service::Locale()->select('ev_service.id','name')->latest()->first();

		if ( $item && $item->id != $request->get('last_id') )
			$result = ['id'=>$item->id,'name'=>$item->name];

		return $result;
	}


}
