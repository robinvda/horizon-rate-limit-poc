<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Tasks</title>

        <meta http-equiv="refresh" content="1">

        <style>
            html {
                font-family: Arial;
            }

            table, th, td {
                border: 1px solid black;
                border-collapse: collapse;
                padding: 2px;
            }
        </style>
    </head>
    <body>
        <div>
            <table>
                <tr>
                    <th style="text-align: left">Supervisor</th>
                    <th style="text-align: left"></th>
                </tr>
                @foreach(\Illuminate\Support\Facades\Redis::command('smembers', ['task-supervisors']) as $supervisor)
                    <tr>
                        <td style="vertical-align: top">{{ $supervisor }}</td>
                        <td>
                            <table>
                                <tr>
                                    <td>Worker</td>
                                    <td>Processed tasks</td>
                                    <td>Processing task</td>
                                </tr>
                                @foreach(\Illuminate\Support\Facades\Redis::command('smembers', ["task-supervisors:$supervisor:workers"]) as $worker)
                                <tr>
                                    <td>{{ $worker }}</td>
                                    <td>{{ \Illuminate\Support\Facades\Redis::command('get', ["task-worker:$worker:processed-tasks"]) }}</td>
                                    <td>{{ \Illuminate\Support\Facades\Redis::command('get', ["task-worker:$worker"]) }}</td>
                                </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                @endforeach
            </table>
            <hr />
            <table>
                <tr>
                    <th>Queue</th>
                    <th>Tasks</th>
                </tr>
                @foreach(\Illuminate\Support\Facades\Redis::command('smembers', [\App\Tasks\Task::REDIS_KEY_QUEUES]) as $queue)
                    <tr>
                        <td>{{ $queue }}</td>
                        <td>{{ \Illuminate\Support\Facades\Redis::command('llen', [$queue]) }}</td>
                    </tr>
                @endforeach
            </table>
            <hr />
            <table>
                <tr>
                    <th>Task</th>
                    <th>Executed</th>
                </tr>
                @foreach(\Illuminate\Support\Facades\Redis::command('KEYS', ["task:*"]) as $task)
                    <tr>
                        <td>{{ $task }}</td>
                        <td>{{ \Illuminate\Support\Facades\Redis::command('get', [explode(config('database.redis.options.prefix'), $task)[1]]) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    </body>
</html>
