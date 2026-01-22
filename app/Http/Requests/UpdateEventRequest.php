<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'sometimes|required|date_format:Y-m-d',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_date' => 'sometimes|required|date_format:Y-m-d|after_or_equal:start_date',
            'end_time' => 'sometimes|required|date_format:H:i',
            'color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'repeat_type' => 'nullable|in:none,daily,weekly,monthly',
            'repeat_end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Event title is required',
            'title.max' => 'Event title must not exceed 255 characters',
            'start_date.required' => 'Start date is required',
            'start_date.date_format' => 'Start date must be in format YYYY-MM-DD',
            'start_time.required' => 'Start time is required',
            'start_time.date_format' => 'Start time must be in format HH:MM',
            'end_date.required' => 'End date is required',
            'end_date.date_format' => 'End date must be in format YYYY-MM-DD',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
            'end_time.required' => 'End time is required',
            'end_time.date_format' => 'End time must be in format HH:MM',
            'color.regex' => 'Color must be a valid hex color code (e.g., #FF0000)',
            'repeat_type.in' => 'Repeat type must be one of: none, daily, weekly, monthly',
            'repeat_end_date.date_format' => 'Repeat end date must be in format YYYY-MM-DD',
            'repeat_end_date.after_or_equal' => 'Repeat end date must be after or equal to start date',
        ];
    }
}
