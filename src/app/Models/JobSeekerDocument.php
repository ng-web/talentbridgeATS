<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class JobSeekerDocument extends Model
{
    public const TYPE_PASSPORT = 'passport';
    public const TYPE_PROFILE_PHOTO = 'profile_photo';
    public const TYPE_DRIVERS_LICENSE = 'drivers_license';
    public const TYPE_CERTIFICATE = 'certificate';
    public const TYPE_POLICE_RECORD = 'police_record';
    public const TYPE_MEDICAL_RECORD = 'medical_record';

    public const TYPES = [
        self::TYPE_PASSPORT,
        self::TYPE_PROFILE_PHOTO,
        self::TYPE_DRIVERS_LICENSE,
        self::TYPE_CERTIFICATE,
        self::TYPE_POLICE_RECORD,
        self::TYPE_MEDICAL_RECORD,
    ];

    public const LABELS = [
        self::TYPE_PASSPORT        => 'Passport (Bio-data Page)',
        self::TYPE_PROFILE_PHOTO   => 'Profile Photo',
        self::TYPE_DRIVERS_LICENSE => "Driver's License",
        self::TYPE_CERTIFICATE     => 'Qualifications / Certificates',
        self::TYPE_POLICE_RECORD   => 'Police Record',
        self::TYPE_MEDICAL_RECORD  => 'Medical Record',
    ];

    public const CATEGORIES = [
        'Identity & Verification' => [
            self::TYPE_PASSPORT,
            self::TYPE_DRIVERS_LICENSE,
        ],
        'Background & Compliance' => [
            self::TYPE_POLICE_RECORD,
            self::TYPE_MEDICAL_RECORD,
        ],
        'Education & Qualification' => [
            self::TYPE_CERTIFICATE,
        ],
    ];

    protected $fillable = [
        'job_seeker_id',
        'document_type',
        'file_path',
        'uploaded_at',
        'notes',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public static function labelFor(string $type): string
    {
        return self::LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function validationRulesFor(string $type): array
    {
        return match ($type) {
            self::TYPE_PROFILE_PHOTO => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            default                  => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        };
    }

    public static function acceptAttrFor(string $type): string
    {
        return match ($type) {
            self::TYPE_PROFILE_PHOTO => '.jpg,.jpeg,.png',
            default                  => '.pdf,.jpg,.jpeg,.png',
        };
    }

    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(JobSeeker::class);
    }
}
