<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeQuestionnaireRequest extends FormRequest
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
        $rules = [
            'responses' => 'required'
        ];
        
        // Add file validation rules - accept all common image and document formats
        // Including mobile camera formats (HEIC, HEIF, WEBP)
        for ($i = 0; $i < 20; $i++) {
            $rules["file_{$i}"] = 'sometimes|file|max:30720|mimes:jpg,jpeg,png,gif,pdf,doc,docx,heic,heif,webp';
        }
        
        return $rules;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'responses.required' => 'Questionnaire responses are required',
            'responses.*.required' => 'All questions must be answered'
        ];
    }
}
