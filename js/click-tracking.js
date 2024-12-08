jQuery(document).ready(function ($) {
    // متغير لتخزين حالة النقر
    let clicked = false;

    $('.phone-icon, .whatsapp-icon').on('click', function (e) {
        if (clicked) {
            return; // إذا كان قد تم النقر بالفعل، لا يتم إرسال البيانات
        }

        clicked = true; // تعيين حالة النقر

        e.preventDefault(); // منع السلوك الافتراضي للرابط

        const number = $(this).attr('href').replace(/tel:|https:\/\/wa\.me\//, ''); // إزالة الـ prefix
        const type = $(this).hasClass('phone-icon') ? 'phone' : 'whatsapp'; // تحديد نوع الرقم
        const post_id = clickTrackingAjax.post_id; // رقم المقال الحالي

        // إرسال البيانات عبر AJAX
        $.post(clickTrackingAjax.ajax_url, {
            action: 'register_contact_click',
            type: type,  // إرسال نوع النقر (هاتف أو واتساب)
            number: number,  // إرسال الرقم
            post_id: post_id,  // إرسال معرف المقالة
        }, function (response) {
            if (response.success) {
                console.log('Click registered successfully:', response.data);
            } else {
                console.error('Error registering click:', response.data);
            }
        });

        // فتح الرابط بعد إرسال البيانات
        window.location.href = $(this).attr('href');

        // إعادة تعيين حالة النقر بعد 1 ثانية (يمكنك تعديل الوقت حسب الحاجة)
        setTimeout(function () {
            clicked = false;
        }, 1000);  // إعادة تفعيل إمكانية النقر بعد ثانية واحدة
    });
});
