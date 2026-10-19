<?php

return [
    'required' => ':attributeは必須です。',
    'email' => ':attributeは有効なメールアドレスで入力してください。',
    'unique' => 'この:attributeはすでに使用されています。',
    'confirmed' => ':attributeの確認が一致しません。',
    'min' => [
        'string' => ':attributeは:min文字以上で入力してください。',
    ],
    'max' => [
        'string' => ':attributeは:max文字以内で入力してください。',
    ],

    'attributes' => [
        'name' => 'お名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'password_confirmation' => 'パスワード確認',
    ],
];