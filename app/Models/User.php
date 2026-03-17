<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use CrudTrait;
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'nickname',
        'username',
        'department_id',
        'division_id',
        'is_active',
        'resolved_tickets_count',
        'email',
        'password',
        'last_login_at',
        'notify_ticket_created',
        'notify_ticket_assigned',
        'notify_ticket_status_changed',
        'notify_ticket_commented',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    // Inside app/Models/User.php

    public function getAvatarUrl()
    {
        if ($this->avatar_url && Storage::disk('public')->exists($this->avatar_url)) {
            return asset('storage/' . $this->avatar_url);
        }

        // Fallback to UI Avatars API
        $name_initials = urlencode($this->name);
        return "https://ui-avatars.com/api/?name={$name_initials}&background=random&color=fff&font-size=0.35&length=3";
    }

    public function getAvatarWebpUrl()
    {
        if ($this->avatar_url) {
            // Example: 'avatars/1.jpg' -> 'avatars/1.webp'
            $webpPath = pathinfo($this->avatar_url, PATHINFO_DIRNAME) . '/'
                        . pathinfo($this->avatar_url, PATHINFO_FILENAME)
                        . '.webp';

            // Only return a path if the WebP file actually exists in storage
            if (Storage::disk('public')->exists($webpPath)) {
                return asset('storage/' . $webpPath);
            }
        }

        // Return an empty string or null if the WebP path is not valid/found.
        return null;
    }

    public function resolvedTickets()
    {
        // Replace 'assigned_to_user_id' with the actual FK in your tickets table
        return $this->hasMany(Ticket::class, 'resolved_by')
                    ->where('status_id', 1); // 1 = Resolved based on your DB
    }

    public function overdueTickets()
    {
        return $this->hasMany(\App\Models\Ticket::class, 'assigned_to')
            ->where(function ($query) {
                $query->where(function ($q) {
                    // Case A: Still open and older than 3 days
                    $q->where('status_id', '!=', 1)
                    ->where('created_at', '<', now()->subDays(3));
                })
                ->orWhere(function ($q) {
                    // Case B: Resolved, but it took more than 72 hours (3 days)
                    $q->where('status_id', 1)
                    ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, resolved_at) > 72');
                });
            });
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function scopeActiveToday($query)
    {
        return $query->where('last_login_at', '>=', now()->startOfDay());
    }

    public function wantsNotification(string $type): bool
    {
        return (bool) ($this->{$type} ?? true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isDeptHead(): bool
    {
        return $this->hasRole('dept_head');
    }

    public function isDivHead(): bool
    {
        return $this->hasRole('div_head');
    }

    public function isHrStaff(): bool
    {
        return $this->hasRole('hr_staff');
    }

    public function isEmployee(): bool
    {
        return $this->hasRole('employee');
    }
}
