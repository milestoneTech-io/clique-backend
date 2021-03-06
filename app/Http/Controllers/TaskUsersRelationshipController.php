<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Services\JSONAPIService;
use App\Notifications\NotifyAssignedUsers;
use App\Notifications\NotifyNewSupervisors;
use App\Notifications\NotifyUnassignedUsers;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NotifyRemovedSupervisors;
use App\Http\Requests\JSONAPIRelationshipRequest;

class TaskUsersRelationshipController extends Controller
{
    private $service;
    public function __construct(JSONAPIService $service)
    {
        $this->service = $service;
    }

    public function index(Task $task)
    {
        return $this->service->fetchRelationship($task, 'assignees');
    }

    /**
     * Assign users to a task
     * 
     * @param \App\Models\Task $task
     * @return \Illuminate\Http\Response
     */
    public function update(JSONAPIRelationshipRequest $request, Task $task)
    {
        $this->service->notificationHandler($request, $task, 'assignees', NotifyAssignedUsers::class, NotifyUnassignedUsers::class, auth()->user());
     
        $task->assignees()->sync($request->input('data.*.id'));
        return response(null, 204);
    }


    /**
     * Make assigned users supervisor
     * 
     * @param \App\Models\Task $task
     * @return \Illuminate\Http\Response
     */
    public function supervisor(JSONAPIRelationshipRequest $request, Task $task)
    {
       
        $task->assignees()->updateExistingPivot($request->input('data.*.id'), [
            'is_supervisor' => 1
        ]);

        $new_supervisors = User::whereIn('id', $request->input('data.*.id'))->get();
        Notification::send($new_supervisors, new NotifyNewSupervisors(auth()->user(), $task));

        return response(null, 200);
    }
    
    /**
     * Make assigned users supervisor
     * 
     * @param \App\Models\Task $task
     * @return \Illuminate\Http\Response
     */
    public function remove_supervisor(JSONAPIRelationshipRequest $request, Task $task)
    {
       
        $task->assignees()->updateExistingPivot($request->input('data.*.id'), [
            'is_supervisor' => 0
        ]);

        $new_supervisors = User::whereIn('id', $request->input('data.*.id'))->get();
        Notification::send($new_supervisors, new NotifyRemovedSupervisors(auth()->user(), $task));

        return response(null, 200);
    }
}
