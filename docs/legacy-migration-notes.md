# Legacy PHP Migration Notes

This project replaces a legacy PHP app bundled in `app.zip`.

## What Was In The Old Zip

The old package contains two separate parts:

- A Flutter web build intended for browser hosting
- A procedural PHP backend with direct MySQL access

## Legacy PHP Endpoints Found

- `login.php`
- `admin/enquiry/add.php`
- `admin/enquiry/get_enquiry.php`
- `admin/enquiry/get_year.php`
- `admin/enquiry/update.php`
- `admin/enquiry/get_enquiry_fee.php`
- `admin/enquiry/get_class.php`
- `admin/admission/list_admission.php`
- `admin/admission/form_no.php`
- `admin/admission/create_student.php`
- `admin/admission/promote_students.php`
- `admin/admission/get_student_details.php`
- `admin/admission/create_academic_record.php`
- `admin/dress/get_dress_fees.php`
- `admin/books/get_book.php`

## Important Risks In The Old Code

- Database credentials were stored directly in `credential.php`
- Authentication used plain-text password checks
- Several endpoints used raw SQL or weak validation patterns
- CORS was fully open with `Access-Control-Allow-Origin: *`
- Some files depend on missing shared helpers such as `admin/_db.php`

## Recommended Hosting Layout

- `shivanshschool.in` -> Laravel backend
- `app.shivanshschool.in` -> Flutter web build

## Recommended Migration Order

1. Move database credentials into Laravel `.env`
2. Recreate login using Laravel authentication or token auth
3. Rebuild enquiry APIs in Laravel controllers
4. Rebuild admission APIs in Laravel controllers
5. Point the Flutter app to the new Laravel API base URL
6. Deploy the Flutter web build separately on `app.shivanshschool.in`

## Suggested Laravel API Groups

- `/api/login`
- `/api/enquiries`
- `/api/admissions`
- `/api/books`
- `/api/dress`

## Notes

Do not copy the old credential file into the new Laravel app.
Use the old PHP files only as reference for field names, responses, and business rules.
