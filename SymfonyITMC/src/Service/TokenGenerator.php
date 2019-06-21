<?php

namespace App\Service;

class TokenGenerator{

    public function generate(){
        return md5(rand().time().uniqid());
    }

}