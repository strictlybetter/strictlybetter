<?php

namespace App\Exceptions;

use Throwable;
use Mail;
use App\Mail\ExceptionOccured;
use App\ErrorLogLine;
use Carbon\Carbon;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Foundation\Validation\ValidationException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);

        if (\App::environment('production') && $this->shouldReport($exception))
            $this->sendEmail($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Throwable $exception)
    {
        return parent::render($request, $exception);
    }

    public function sendEmail(Throwable $exception)
    {
        try {
            $author = config('externals.author_email');
            if (!$author)
                return;

            if ($this->hasRecentlyOccurred($exception))
                return;

            // Mail error
            $content['message'] = $exception->getMessage();
            $content['file'] = $exception->getFile();
            $content['line'] = $exception->getLine();
            $content['trace'] = $exception->getTrace();
            $content['url'] = request()->url();
            $content['body'] = request()->all();

            Mail::to($author)->send(new ExceptionOccured($content));

        } catch (Throwable $exception) {
            \Log::error($exception);
        }
    }

    public function hasRecentlyOccurred(Throwable $exception) {

        $dayAgo = Carbon::now()->subDay();

        // If this error has occurred recently, don't mail it.
        $recentError = ErrorLogLine::where('line', $exception->getLine())
                                ->where('file', $exception->getFile())
                                ->where('created_at', '>=', $dayAgo)
                                ->first();
        if ($recentError)
            return true;

        // Prune old errors
        ErrorLogLine::where('created_at', '<', $dayAgo)->delete();

        // Save this error
        $errorLogLine = new ErrorLogLine();
        $errorLogLine->file = $exception->getFile();
        $errorLogLine->line = $exception->getLine();
        $errorLogLine->save();

        return false;
    }
}
