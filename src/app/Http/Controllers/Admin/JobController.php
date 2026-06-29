<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\JobApprovedMail;
use App\Models\Job;
use App\Notifications\JobApprovedNotification;
use App\Notifications\JobReturnedToPendingNotification;
use App\Notifications\JobArchivedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

final class JobController extends Controller
{
    public function index(Request $request): View|\Illuminate\Contracts\View\View|\Illuminate\Http\Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $jobs = Job::query()
            ->with(['employer.user'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($subQuery) use ($q) {
                    $subQuery
                        ->where('title', 'like', "%{$q}%")
                        ->orWhere('category', 'like', "%{$q}%")
                        ->orWhere('location', 'like', "%{$q}%")
                        ->orWhereHas('employer', function ($employerQuery) use ($q) {
                            $employerQuery
                                ->where('company_name', 'like', "%{$q}%")
                                ->orWhereHas('user', function ($userQuery) use ($q) {
                                    $userQuery->where('name', 'like', "%{$q}%");
                                });
                        });
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $data = [
            'jobs' => $jobs,
            'filters' => [
                'q' => $q,
                'status' => $status,
            ],
        ];

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->view('admin.jobs.partials.list', $data);
        }

        return view('admin.jobs.index', $data);
    }

    public function approve(Job $job): RedirectResponse
    {
        $job->update([
            'status' => Job::STATUS_PUBLISHED,
            'is_approved' => true,
        ]);

        $employer = $job->employer;
        $employerUser = $employer?->user;

        if ($employerUser) {
            $employerUser->notify(new JobApprovedNotification($job));

            if ($employer?->notificationEmail()) {
                Mail::to($employer->notificationEmail())->send(new JobApprovedMail($job));
            }
        }

        return back()->with('success', 'Job approved successfully.');
    }

    public function setPending(Job $job): RedirectResponse
    {
        $job->update([
            'status' => Job::STATUS_PENDING_REVIEW,
            'is_approved' => false,
        ]);

        $employerUser = $job->employer?->user;

        if ($employerUser) {
            $employerUser->notify(new JobReturnedToPendingNotification($job));
        }

        return back()->with('success', 'Job moved to pending review.');
    }

    public function archive(Job $job): RedirectResponse
    {
        $job->update([
            'status' => Job::STATUS_ARCHIVED,
        ]);

        $employerUser = $job->employer?->user;

        if ($employerUser) {
            $employerUser->notify(new JobArchivedNotification($job));
        }

        return back()->with('success', 'Job archived successfully.');
    }
}
