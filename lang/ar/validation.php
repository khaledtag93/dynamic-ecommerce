<?php
return [
'required' => 'حقل :attribute مطلوب.',
'numeric' => 'يجب أن يكون :attribute رقمًا.',
'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.',
'string' => 'يجب أن يكون :attribute نصًا.',
'email' => 'يجب أن يكون :attribute بريدًا إلكترونيًا صالحًا.',
'exists' => ':attribute المحدد غير صالح.',
'unique' => 'قيمة :attribute مستخدمة بالفعل.',
'in' => ':attribute المحدد غير صالح.',
'max' => ['numeric' => 'يجب ألا تكون قيمة :attribute أكبر من :max.', 'string' => 'يجب ألا يزيد :attribute عن :max حرفًا.'],
'min' => ['numeric' => 'يجب ألا تكون قيمة :attribute أقل من :min.', 'string' => 'يجب ألا يقل :attribute عن :min أحرف.'],
'attributes' => ['barcode'=>'الباركود','sku'=>'رمز الصنف','name'=>'الاسم','email'=>'البريد الإلكتروني','phone'=>'رقم الهاتف','quantity'=>'الكمية','price'=>'السعر','payment_method'=>'طريقة الدفع','cash_received'=>'النقد المستلم','opening_cash'=>'الرصيد الافتتاحي','closing_cash_counted'=>'النقد المعدود','customer_name'=>'اسم العميل','notes'=>'الملاحظات','discount_type'=>'نوع الخصم','discount_value'=>'قيمة الخصم','discount_reason'=>'سبب الخصم'],
];
