<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\EmailNotificationSetting;

class DailyScheduleNotification extends BaseNotification
{


    /**
     * Create a new notification instance.
     */

    private $userData;
    private $userId;
    private $userModules;

    public function __construct($userData, $userId)
    {
        $this->userData = $userData;
        $this->userId = $userId;
        $this->company = $this->userData['user'][$this->userId]->company;
        $this->userModules = $this->userModules($userId);
        $this->emailSetting = EmailNotificationSetting::where('company_id', $this->company->id)->where('slug', 'daily-schedule-notification')->first();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        $via = [];

        $modulesToCheck = ['tasks', 'events', 'holidays', 'leaves', 'recruit'];

        if (!empty(array_intersect($modulesToCheck, $this->userModules))) {
            if ($this->emailSetting->send_slack == 'yes') {
                array_push($via, 'mail');
            }
        }

        return $via;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $build = parent::build();
        $taskUrl = getDomainSpecificUrl(route('dashboard'), $this->company);
        $eventUrl = getDomainSpecificUrl(route('dashboard'), $this->company);
        $holidayUrl = getDomainSpecificUrl(route('dashboard'), $this->company);
        $leaveUrl = getDomainSpecificUrl(route('dashboard'), $this->company);

        $content = __('email.dailyScheduleReminder.content') . ':<br>';

        if (in_array('tasks', $this->userModules)) {
            $content .= '<br>' . __('email.dailyScheduleReminder.taskText') . ': <a class="text-dark-grey text-decoration-none" href=' . $taskUrl . '> ' . $this->userData['tasks'][$this->userId] . '</a>';
        }

        if (in_array('events', $this->userModules)) {
            $content .= '<br>' . __('email.dailyScheduleReminder.eventText') . ': <a class="text-dark-grey" href=' . $eventUrl . '> ' . $this->userData['events'][$this->userId] . '</a>';
        }

        if (in_array('holidays', $this->userModules)) {
            $content .= '<br>' . __('email.dailyScheduleReminder.holidayText') . ': <a class="text-dark-grey" href=' . $holidayUrl . '> ' . $this->userData['holidays'][$this->userId] . '</a>';
        }

        if (in_array('leaves', $this->userModules)) {
            $content .= '<br>' . __('email.dailyScheduleReminder.leavesText') . ': <a class="text-dark-grey text-decoration-none" href=' . $leaveUrl . '> ' . $this->userData['leaves'][$this->userId] . '</a>';
        }


        if (module_enabled('Recruit') && in_array('recruit', $this->userModules)) {
            $interviewUrl = getDomainSpecificUrl(route('dashboard'), $this->company);

            $content .= '<br>' . __('email.dailyScheduleReminder.interviewText') . ': <a class="text-dark-grey text-decoration-none" href=' . $interviewUrl . '> ' . $this->userData['interview'][$this->userId] . '</a>';
        }

        return $build
            ->subject(__('email.dailyScheduleReminder.subject', ['date' => now()->format($this->company->date_format)]))
            ->markdown('mail.email', [
                'notifiableName' => $this->userData['user'][$this->userId]->name,
                'content' => $content
            ]);
    }

    public function userModules($userId)
    {
        $userData = User::find($userId);
        $roles = $userData->roles;
        $userRoles = $roles->pluck('name')->toArray();

        $module = new \App\Models\ModuleSetting();

        if (in_array('admin', $userRoles)) {
            $module = $module->where('type', 'admin');

        }
        elseif (in_array('employee', $userRoles)) {
            $module = $module->where('type', 'employee');
        }

        $module = $module->where('status', 'active');
        $module->select('module_name');

        $module = $module->get();
        $moduleArray = [];

        foreach ($module->toArray() as $item) {
            $moduleArray[] = array_values($item)[0];
        }

        return $moduleArray;

    }

}
