<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_id',
        'code',
        'name',
        'description',
        'degree_type',
        'duration_years',
        'contact_email',
        'contact_phone',
        'is_active',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'duration_years' => 'decimal:1',
        'sort_order' => 'integer',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Set default sort order when creating
        static::creating(function ($program) {
            if (is_null($program->sort_order)) {
                $program->sort_order = static::where('school_id', $program->school_id)
                    ->max('sort_order') + 1;
            }
        });
    }

    /**
     * Get the school that owns the program.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the units for the program.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Get the enrollments for the program.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get the classes for the program through units.
     */
    public function classes()
    {
        return $this->hasManyThrough(
            ProgramClass::class,
            Unit::class,
            'program_id',
            'unit_id'
        );
    }

    /**
     * Scope a query to only include active programs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include programs for a specific school.
     */
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope a query to only include programs of a specific degree type.
     */
    public function scopeOfDegreeType($query, $degreeType)
    {
        return $query->where('degree_type', $degreeType);
    }

    /**
     * Scope a query to search programs by name or code.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('degree_type', 'like', "%{$search}%");
        });
    }

    /**
     * Get the full name attribute (degree type + name).
     */
    public function getFullNameAttribute(): string
    {
        return $this->degree_type . ' in ' . $this->name;
    }

    /**
     * Get the program code with school prefix.
     */
    public function getCodeWithSchoolAttribute(): string
    {
        return ($this->school->code ?? 'UNK') . '-' . $this->code;
    }

    /**
     * Get the display name (code + name).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->code . ' - ' . $this->name;
    }

    /**
     * Check if the program has any active units.
     */
    public function hasActiveUnits(): bool
    {
        return $this->units()->where('is_active', true)->exists();
    }

    /**
     * Check if the program has any enrollments.
     */
    public function hasEnrollments(): bool
    {
        return $this->enrollments()->exists();
    }

    /**
     * Get the number of active units.
     */
    public function getActiveUnitsCountAttribute(): int
    {
        return $this->units()->where('is_active', true)->count();
    }

    /**
     * Get the number of current enrollments.
     */
    public function getCurrentEnrollmentsCountAttribute(): int
    {
        // Assuming there's a current semester
        $currentSemester = Semester::where('is_active', true)->first();
        
        if (!$currentSemester) {
            return 0;
        }

        return $this->enrollments()
            ->where('semester_id', $currentSemester->id)
            ->count();
    }

    /**
     * Get programs by degree type for the school.
     */
    public static function getByDegreeTypeForSchool($schoolId, $degreeType = null)
    {
        $query = static::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($degreeType) {
            $query->where('degree_type', $degreeType);
        }

        return $query->get();
    }

    /**
     * Get available degree types.
     */
    public static function getDegreeTypes(): array
    {
        return [
            'Certificate' => 'Certificate',
            'Diploma' => 'Diploma',
            'Bachelor' => 'Bachelor\'s Degree',
            'Master' => 'Master\'s Degree',
            'PhD' => 'Doctoral Degree (PhD)',
        ];
    }

    /**
     * Get degree type display name.
     */
    public function getDegreeTypeDisplayAttribute(): string
    {
        $types = static::getDegreeTypes();
        return $types[$this->degree_type] ?? $this->degree_type;
    }

    /**
     * Get the duration in human readable format.
     */
    public function getDurationDisplayAttribute(): string
    {
        if ($this->duration_years == 1) {
            return '1 year';
        } elseif ($this->duration_years < 1) {
            $months = $this->duration_years * 12;
            return $months . ' months';
        } else {
            return $this->duration_years . ' years';
        }
    }

    /**
     * Get program statistics.
     */
    public function getStatsAttribute(): array
    {
        return [
            'units_count' => $this->units()->count(),
            'active_units_count' => $this->units()->where('is_active', true)->count(),
            'enrollments_count' => $this->enrollments()->count(),
            'current_enrollments_count' => $this->getCurrentEnrollmentsCountAttribute(),
        ];
    }

    /**
     * Check if program can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return !$this->hasEnrollments() && $this->units()->count() === 0;
    }

    /**
     * Get validation rules for program creation/update.
     */
    public static function getValidationRules($programId = null, $schoolId = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'degree_type' => 'required|string|in:Certificate,Diploma,Bachelor,Master,PhD',
            'duration_years' => 'required|numeric|min:0.5|max:10',
            'description' => 'nullable|string',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];

        // Code validation with uniqueness within school
        if ($schoolId) {
            $codeRule = 'required|string|max:20';
            if ($programId) {
                $codeRule .= '|unique:programs,code,' . $programId . ',id,school_id,' . $schoolId;
            } else {
                $codeRule .= '|unique:programs,code,NULL,id,school_id,' . $schoolId;
            }
            $rules['code'] = $codeRule;
        } else {
            $rules['code'] = 'required|string|max:20';
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     */
    public static function getValidationMessages(): array
    {
        return [
            'code.unique' => 'This program code already exists in the selected school.',
            'degree_type.in' => 'Please select a valid degree type.',
            'duration_years.min' => 'Program duration must be at least 0.5 years (6 months).',
            'duration_years.max' => 'Program duration cannot exceed 10 years.',
            'contact_email.email' => 'Please provide a valid email address.',
        ];
    }

    /**
     * Format program for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'full_name' => $this->getFullNameAttribute(),
            'display_name' => $this->getDisplayNameAttribute(),
            'degree_type' => $this->degree_type,
            'degree_type_display' => $this->getDegreeTypeDisplayAttribute(),
            'duration_years' => $this->duration_years,
            'duration_display' => $this->getDurationDisplayAttribute(),
            'is_active' => $this->is_active,
            'school' => [
                'id' => $this->school->id,
                'name' => $this->school->name,
                'code' => $this->school->code,
            ],
            'stats' => $this->getStatsAttribute(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}