<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeePersonalInfoRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Will be handled by middleware authentication
    }

    public function rules()
    {
        return [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255', 
            'phone' => 'sometimes|nullable|string|regex:/^\+1 \(\d{3}\) \d{3}-\d{4}$/|max:18',
            'address' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:100',
            'state' => 'sometimes|required|string|max:100',
            'zip_code' => 'sometimes|required|string|max:20',
            'country' => 'sometimes|required|string|max:100',
            'requested_hours' => 'sometimes|required|integer|min:1|max:40',
            'flexible_hours' => 'sometimes|boolean',
            'emergency_contact' => 'sometimes|nullable|string|max:255',
            'emergency_phone' => 'sometimes|nullable|string|regex:/^\+1 \(\d{3}\) \d{3}-\d{4}$/|max:18',
        ];
    }

    public function messages()
    {
        return [
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'address.required' => 'Street address is required',
            'address.max' => 'Street address must not exceed 255 characters',
            'city.required' => 'City is required',
            'city.max' => 'City must not exceed 100 characters',
            'state.required' => 'State is required',
            'state.max' => 'State must not exceed 100 characters',
            'zip_code.required' => 'Zip code is required',
            'zip_code.max' => 'Zip code must not exceed 20 characters',
            'country.required' => 'Country is required',
            'country.max' => 'Country must not exceed 100 characters',
            'requested_hours.required' => 'Requested hours per week is required',
            'requested_hours.integer' => 'Requested hours must be a number',
            'requested_hours.min' => 'Requested hours must be at least 1',
            'requested_hours.max' => 'Requested hours cannot exceed 40',
            'phone.regex' => 'Phone number must be in valid US format: +1 (XXX) XXX-XXXX',
            'phone.max' => 'Phone number must not exceed 18 characters',
            'emergency_phone.regex' => 'Emergency phone must be in valid US format: +1 (XXX) XXX-XXXX',
            'emergency_phone.max' => 'Emergency phone number must not exceed 18 characters',
            'emergency_contact.max' => 'Emergency contact name must not exceed 255 characters',
        ];
    }
}
