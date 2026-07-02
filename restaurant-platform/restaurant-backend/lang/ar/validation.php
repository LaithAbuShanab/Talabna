<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'يجب قبول حقل :attribute.',
    'accepted_if' => 'يجب قبول حقل :attribute عندما يكون :other هو :value.',
    'active_url' => 'يجب أن يكون حقل :attribute رابطًا صالحًا.',
    'after' => 'يجب أن يكون حقل :attribute تاريخًا بعد :date.',
    'after_or_equal' => 'يجب أن يكون حقل :attribute تاريخًا بعد أو يساوي :date.',
    'alpha' => 'يجب أن يحتوي حقل :attribute على أحرف فقط.',
    'alpha_dash' => 'يجب أن يحتوي حقل :attribute على أحرف وأرقام وشرطات وشرطات سفلية فقط.',
    'alpha_num' => 'يجب أن يحتوي حقل :attribute على أحرف وأرقام فقط.',
    'any_of' => 'حقل :attribute غير صالح.',
    'array' => 'يجب أن يكون حقل :attribute مصفوفة.',
    'ascii' => 'يجب أن يحتوي حقل :attribute على أحرف ورموز أحادية البايت فقط.',
    'before' => 'يجب أن يكون حقل :attribute تاريخًا قبل :date.',
    'before_or_equal' => 'يجب أن يكون حقل :attribute تاريخًا قبل أو يساوي :date.',
    'between' => [
        'array' => 'يجب أن يحتوي حقل :attribute على عدد عناصر يتراوح بين :min و :max.',
        'file' => 'يجب أن يكون حجم حقل :attribute بين :min و :max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute بين :min و :max.',
        'string' => 'يجب أن يكون طول حقل :attribute بين :min و :max حرفًا.',
    ],
    'boolean' => 'يجب أن تكون قيمة حقل :attribute صحيحة أو خاطئة.',
    'can' => 'يحتوي حقل :attribute على قيمة غير مصرح بها.',
    'confirmed' => 'تأكيد حقل :attribute غير مطابق.',
    'contains' => 'يفتقد حقل :attribute إلى قيمة مطلوبة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => 'يجب أن يكون حقل :attribute تاريخًا صالحًا.',
    'date_equals' => 'يجب أن يكون حقل :attribute تاريخًا مساويًا لـ :date.',
    'date_format' => 'يجب أن يتطابق حقل :attribute مع الصيغة :format.',
    'decimal' => 'يجب أن يحتوي حقل :attribute على :decimal منزلة عشرية.',
    'declined' => 'يجب رفض حقل :attribute.',
    'declined_if' => 'يجب رفض حقل :attribute عندما يكون :other هو :value.',
    'different' => 'يجب أن يكون حقل :attribute و :other مختلفين.',
    'digits' => 'يجب أن يتكون حقل :attribute من :digits أرقام.',
    'digits_between' => 'يجب أن يتكون حقل :attribute من عدد أرقام يتراوح بين :min و :max.',
    'dimensions' => 'أبعاد صورة حقل :attribute غير صالحة.',
    'distinct' => 'حقل :attribute يحتوي على قيمة مكررة.',
    'doesnt_contain' => 'يجب ألا يحتوي حقل :attribute على أي من القيم التالية: :values.',
    'doesnt_end_with' => 'يجب ألا ينتهي حقل :attribute بأي من القيم التالية: :values.',
    'doesnt_start_with' => 'يجب ألا يبدأ حقل :attribute بأي من القيم التالية: :values.',
    'email' => 'يجب أن يكون حقل :attribute بريدًا إلكترونيًا صالحًا.',
    'encoding' => 'يجب أن يكون حقل :attribute مُرمّزًا بـ :encoding.',
    'ends_with' => 'يجب أن ينتهي حقل :attribute بأحد القيم التالية: :values.',
    'enum' => 'القيمة المختارة لحقل :attribute غير صالحة.',
    'exists' => 'القيمة المختارة لحقل :attribute غير صالحة.',
    'extensions' => 'يجب أن يكون لحقل :attribute أحد الامتدادات التالية: :values.',
    'file' => 'يجب أن يكون حقل :attribute ملفًا.',
    'filled' => 'يجب أن يحتوي حقل :attribute على قيمة.',
    'gt' => [
        'array' => 'يجب أن يحتوي حقل :attribute على أكثر من :value عنصر.',
        'file' => 'يجب أن يكون حجم حقل :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أكبر من :value.',
        'string' => 'يجب أن يكون طول حقل :attribute أكبر من :value حرفًا.',
    ],
    'gte' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :value عنصر أو أكثر.',
        'file' => 'يجب أن يكون حجم حقل :attribute أكبر من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أكبر من أو تساوي :value.',
        'string' => 'يجب أن يكون طول حقل :attribute أكبر من أو يساوي :value حرفًا.',
    ],
    'hex_color' => 'يجب أن يكون حقل :attribute لونًا سداسيًا صالحًا.',
    'image' => 'يجب أن يكون حقل :attribute صورة.',
    'in' => 'القيمة المختارة لحقل :attribute غير صالحة.',
    'in_array' => 'يجب أن يكون حقل :attribute موجودًا ضمن :other.',
    'in_array_keys' => 'يجب أن يحتوي حقل :attribute على واحد على الأقل من المفاتيح التالية: :values.',
    'integer' => 'يجب أن يكون حقل :attribute عددًا صحيحًا.',
    'ip' => 'يجب أن يكون حقل :attribute عنوان IP صالحًا.',
    'ipv4' => 'يجب أن يكون حقل :attribute عنوان IPv4 صالحًا.',
    'ipv6' => 'يجب أن يكون حقل :attribute عنوان IPv6 صالحًا.',
    'json' => 'يجب أن يكون حقل :attribute نص JSON صالح.',
    'list' => 'يجب أن يكون حقل :attribute قائمة.',
    'lowercase' => 'يجب أن يكون حقل :attribute بحروف صغيرة.',
    'lt' => [
        'array' => 'يجب أن يحتوي حقل :attribute على أقل من :value عنصر.',
        'file' => 'يجب أن يكون حجم حقل :attribute أقل من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أقل من :value.',
        'string' => 'يجب أن يكون طول حقل :attribute أقل من :value حرفًا.',
    ],
    'lte' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :value عنصر.',
        'file' => 'يجب أن يكون حجم حقل :attribute أقل من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أقل من أو تساوي :value.',
        'string' => 'يجب أن يكون طول حقل :attribute أقل من أو يساوي :value حرفًا.',
    ],
    'mac_address' => 'يجب أن يكون حقل :attribute عنوان MAC صالحًا.',
    'max' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max عنصر.',
        'file' => 'يجب ألا يكون حجم حقل :attribute أكبر من :max كيلوبايت.',
        'numeric' => 'يجب ألا تكون قيمة حقل :attribute أكبر من :max.',
        'string' => 'يجب ألا يكون طول حقل :attribute أكبر من :max حرفًا.',
    ],
    'max_digits' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max رقم.',
    'mimes' => 'يجب أن يكون حقل :attribute ملفًا من نوع: :values.',
    'mimetypes' => 'يجب أن يكون حقل :attribute ملفًا من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :min عنصر على الأقل.',
        'file' => 'يجب أن يكون حجم حقل :attribute :min كيلوبايت على الأقل.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute :min على الأقل.',
        'string' => 'يجب أن يكون طول حقل :attribute :min حرفًا على الأقل.',
    ],
    'min_digits' => 'يجب أن يحتوي حقل :attribute على :min رقم على الأقل.',
    'missing' => 'يجب أن يكون حقل :attribute مفقودًا.',
    'missing_if' => 'يجب أن يكون حقل :attribute مفقودًا عندما يكون :other هو :value.',
    'missing_unless' => 'يجب أن يكون حقل :attribute مفقودًا إلا إذا كان :other هو :value.',
    'missing_with' => 'يجب أن يكون حقل :attribute مفقودًا عند وجود :values.',
    'missing_with_all' => 'يجب أن يكون حقل :attribute مفقودًا عند وجود :values.',
    'multiple_of' => 'يجب أن تكون قيمة حقل :attribute من مضاعفات :value.',
    'not_in' => 'القيمة المختارة لحقل :attribute غير صالحة.',
    'not_regex' => 'صيغة حقل :attribute غير صالحة.',
    'numeric' => 'يجب أن يكون حقل :attribute رقمًا.',
    'password' => [
        'letters' => 'يجب أن يحتوي حقل :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن يحتوي حقل :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن يحتوي حقل :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن يحتوي حقل :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'تم العثور على :attribute المُدخلة ضمن بيانات مسربة. يرجى اختيار :attribute مختلفة.',
    ],
    'present' => 'يجب أن يكون حقل :attribute موجودًا.',
    'present_if' => 'يجب أن يكون حقل :attribute موجودًا عندما يكون :other هو :value.',
    'present_unless' => 'يجب أن يكون حقل :attribute موجودًا إلا إذا كان :other هو :value.',
    'present_with' => 'يجب أن يكون حقل :attribute موجودًا عند وجود :values.',
    'present_with_all' => 'يجب أن يكون حقل :attribute موجودًا عند وجود :values.',
    'prohibited' => 'حقل :attribute محظور.',
    'prohibited_if' => 'حقل :attribute محظور عندما يكون :other هو :value.',
    'prohibited_if_accepted' => 'حقل :attribute محظور عندما يكون :other مقبولاً.',
    'prohibited_if_declined' => 'حقل :attribute محظور عندما يكون :other مرفوضًا.',
    'prohibited_unless' => 'حقل :attribute محظور إلا إذا كان :other ضمن :values.',
    'prohibits' => 'يمنع حقل :attribute وجود :other.',
    'regex' => 'صيغة حقل :attribute غير صالحة.',
    'required' => 'حقل :attribute مطلوب.',
    'required_array_keys' => 'يجب أن يحتوي حقل :attribute على إدخالات لـ: :values.',
    'required_if' => 'حقل :attribute مطلوب عندما يكون :other هو :value.',
    'required_if_accepted' => 'حقل :attribute مطلوب عندما يكون :other مقبولاً.',
    'required_if_declined' => 'حقل :attribute مطلوب عندما يكون :other مرفوضًا.',
    'required_unless' => 'حقل :attribute مطلوب إلا إذا كان :other ضمن :values.',
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_with_all' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_without' => 'حقل :attribute مطلوب عند عدم وجود :values.',
    'required_without_all' => 'حقل :attribute مطلوب عند عدم وجود أي من :values.',
    'same' => 'يجب أن يتطابق حقل :attribute مع :other.',
    'size' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :size عنصر.',
        'file' => 'يجب أن يكون حجم حقل :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute :size.',
        'string' => 'يجب أن يكون طول حقل :attribute :size حرفًا.',
    ],
    'starts_with' => 'يجب أن يبدأ حقل :attribute بأحد القيم التالية: :values.',
    'string' => 'يجب أن يكون حقل :attribute نصًا.',
    'timezone' => 'يجب أن يكون حقل :attribute منطقة زمنية صالحة.',
    'unique' => 'قيمة حقل :attribute مُستخدمة من قبل.',
    'uploaded' => 'فشل رفع حقل :attribute.',
    'uppercase' => 'يجب أن يكون حقل :attribute بحروف كبيرة.',
    'url' => 'يجب أن يكون حقل :attribute رابطًا صالحًا.',
    'ulid' => 'يجب أن يكون حقل :attribute رمز ULID صالحًا.',
    'uuid' => 'يجب أن يكون حقل :attribute رمز UUID صالحًا.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'device_name' => 'اسم الجهاز',
        'current_password' => 'كلمة المرور الحالية',
        'token' => 'رمز إعادة التعيين',
        'address_line1' => 'العنوان (السطر الأول)',
        'address_line2' => 'العنوان (السطر الثاني)',
        'city' => 'المدينة',
        'label' => 'التسمية',
        'notes' => 'ملاحظات',
        'latitude' => 'خط العرض',
        'longitude' => 'خط الطول',
        'is_default' => 'العنوان الافتراضي',
        'zone_id' => 'منطقة التوصيل',
        'category_id' => 'التصنيف',
        'search' => 'بحث',
        'per_page' => 'عدد العناصر في الصفحة',
        'page' => 'الصفحة',
        'items' => 'عناصر السلة',
        'items.*.product_id' => 'المنتج',
        'items.*.quantity' => 'الكمية',
        'delivery_type' => 'نوع التوصيل',
        'delivery_zone_id' => 'منطقة التوصيل',
        'coupon_code' => 'رمز الكوبون',
    ],

];
