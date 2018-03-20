<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\Api\SocialAuthorizationRequest;

class AuthorizationsController extends Controller
{
    public function socialStore($type, SocialAuthorizationRequest $request)
    {
        if(!in_array($type, ['weixin']))
        {
            return $this->response->errorBadRequest();
        }

        $driver = \Socialite::driver($type);

        try{
            /*
                $access_token 时效性 7200s
                $code 登录确认码 每次访问都会变化
            */
            if ($code = $request->code) {
                $response = $driver->getAccessTokenResponse($code);
                $token = array_get($response, 'access_token');

            }else{
                $token = $request->access_token;

                if($type == 'weixin')
                {
                    $driver->setOpenId($request->openid);
                }

            }

            $oauthUser = $driver->userFromToken($token);
        }catch(\Exception $e)
        {
            return $this->response->errorUnauthorized('参数错误，未获取用户信息');
        }

        //创建用户
        switch ($type) {
            case 'weixin':
                $unionid = $oauthUser->offsetExists('unionid') ? $oauthUser->offsetGet('unionid') : null;
                if($unionid)
                {
                    $user = User::where('weixin_unionid',$unionid)->first();
                }else
                {

                    $user = User::where('weixin_openid',$oauthUser->getId())->first();
                }

                //没有用户创建一个
                if(!$user)
                {
                    $user = User::create([
                        'name' => $oauthUser->getNickname(),
                        'avatar' => $oauthUser->getAvatar(),
                        'weixin_openid' => $oauthUser->getId(),
                        'weixin_unionid' => $unionid,
                    ]);
                }
                break;


        }

        return $this->response->array(['token'=> $user->id]);







    }
}
