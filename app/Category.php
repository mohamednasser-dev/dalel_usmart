<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['image', 'title_en', 'title_ar', 'deleted'];

    public function products() {
        return $this->hasMany('App\Product', 'category_id');
    }

    public function Sub_categories()
    {
        if (session('api_lang') == 'en') {
            return $this->hasMany('App\SubCategory','category_id', 'id')->where('deleted',0)
            ->select('id' , 'title_en as title','image','category_id');
        }else{
            return $this->hasMany('App\SubCategory','category_id', 'id')->where('deleted',0)
            ->select('id' , 'title_ar as title','image','category_id');
        }
        
    }
}
