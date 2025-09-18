<?php
namespace App\Http\Controllers;

use App\Models\Faq;
use Inertia\Inertia;

class FaqController extends Controller
{
    public function __invoke()
    {
        $faqs = Faq::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->get(['id', 'question', 'answer'])
            ->map(fn ($f) => [
                'question' => $f->question,
                'answer'   => $f->answer,           // v-html в AccordionContent
                'value'    => "item-{$f->id}",
            ]);

        return Inertia::render('Your/FaqPage', ['faqs' => $faqs]);
    }
}