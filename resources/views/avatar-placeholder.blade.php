{{-- Credit: Heroicons (https://heroicons.com) --}}

@props([
    'variant' => 'outline',
    'image' => null, // show an image instead of initials
    'name' => null, // either two letters or two words that will be used to create the initials
    'color' => 'zinc',
])

@php
// if character count is 2, use that as intials, otherwise use the first character of the first and last word
$name = Str::of($name)->upper()->trim();
if(strlen($name) !== 2){
    $names = Str::of($name)->explode(' ');
    $name = Str::substr($names->first(), 0,1) . Str::substr($names->last(), 0,1);
}


$classes = Flux::classes('shrink-0')
    ->add(match($variant) {
        'outline' => '[:where(&)]:size-8 rounded-md',
        'solid' => '[:where(&)]:size-8 rounded-md',
        'mini' => '[:where(&)]:size-6 rounded-md',
        'micro' => '[:where(&)]:size-4 rounded-md',
    })
    ->add($variant === 'solid' ? match ($color) {
        'zinc' => 'text-zinc-600 [&_button]:!text-zinc-600 dark:text-zinc-200 [&_button]:dark:!text-zinc-200 bg-zinc-400/15 dark:bg-zinc-400/40 [&:is(button)]:hover:bg-zinc-400/25 [&:is(button)]:hover:dark:bg-zinc-400/50',
        'red' => 'text-red-600 [&_button]:!text-red-600 dark:text-red-200 [&_button]:dark:!text-red-200 bg-red-400/20 dark:bg-red-400/40 [&:is(button)]:hover:bg-red-400/30 [&:is(button)]:hover:dark:bg-red-400/50',
        'orange' => 'text-orange-600 [&_button]:!text-orange-600 dark:text-orange-200 [&_button]:dark:!text-orange-200 bg-orange-400/20 dark:bg-orange-400/40 [&:is(button)]:hover:bg-orange-400/30 [&:is(button)]:hover:dark:bg-orange-400/50',
        'amber' => 'text-amber-600 [&_button]:!text-amber-600 dark:text-amber-200 [&_button]:dark:!text-amber-200 bg-amber-400/25 dark:bg-amber-400/40 [&:is(button)]:hover:bg-amber-400/40 [&:is(button)]:hover:dark:bg-amber-400/50',
        'yellow' => 'text-yellow-700 [&_button]:!text-yellow-700 dark:text-yellow-200 [&_button]:dark:!text-yellow-200 bg-yellow-400/25 dark:bg-yellow-400/40 [&:is(button)]:hover:bg-yellow-400/40 [&:is(button)]:hover:dark:bg-yellow-400/50',
        'lime' => 'text-lime-700 [&_button]:!text-lime-700 dark:text-lime-200 [&_button]:dark:!text-lime-200 bg-lime-400/25 dark:bg-lime-400/40 [&:is(button)]:hover:bg-lime-400/35 [&:is(button)]:hover:dark:bg-lime-400/50',
        'green' => 'text-green-700 [&_button]:!text-green-700 dark:text-green-200 [&_button]:dark:!text-green-200 bg-green-400/20 dark:bg-green-400/40 [&:is(button)]:hover:bg-green-400/30 [&:is(button)]:hover:dark:bg-green-400/50',
        'emerald' => 'text-emerald-700 [&_button]:!text-emerald-700 dark:text-emerald-200 [&_button]:dark:!text-emerald-200 bg-emerald-400/20 dark:bg-emerald-400/40 [&:is(button)]:hover:bg-emerald-400/30 [&:is(button)]:hover:dark:bg-emerald-400/50',
        'teal' => 'text-teal-700 [&_button]:!text-teal-700 dark:text-teal-200 [&_button]:dark:!text-teal-200 bg-teal-400/20 dark:bg-teal-400/40 [&:is(button)]:hover:bg-teal-400/30 [&:is(button)]:hover:dark:bg-teal-400/50',
        'cyan' => 'text-cyan-700 [&_button]:!text-cyan-700 dark:text-cyan-200 [&_button]:dark:!text-cyan-200 bg-cyan-400/20 dark:bg-cyan-400/40 [&:is(button)]:hover:bg-cyan-400/30 [&:is(button)]:hover:dark:bg-cyan-400/50',
        'sky' => 'text-sky-700 [&_button]:!text-sky-700 dark:text-sky-200 [&_button]:dark:!text-sky-200 bg-sky-400/20 dark:bg-sky-400/40 [&:is(button)]:hover:bg-sky-400/30 [&:is(button)]:hover:dark:bg-sky-400/50',
        'blue' => 'text-blue-700 [&_button]:!text-blue-700 dark:text-blue-200 [&_button]:dark:!text-blue-200 bg-blue-400/20 dark:bg-blue-400/40 [&:is(button)]:hover:bg-blue-400/30 [&:is(button)]:hover:dark:bg-blue-400/50',
        'indigo' => 'text-indigo-600 [&_button]:!text-indigo-600 dark:text-indigo-200 [&_button]:dark:!text-indigo-200 bg-indigo-400/20 dark:bg-indigo-400/40 [&:is(button)]:hover:bg-indigo-400/30 [&:is(button)]:hover:dark:bg-indigo-400/50',
        'violet' => 'text-violet-600 [&_button]:!text-violet-600 dark:text-violet-200 [&_button]:dark:!text-violet-200 bg-violet-400/20 dark:bg-violet-400/40 [&:is(button)]:hover:bg-violet-400/30 [&:is(button)]:hover:dark:bg-violet-400/50',
        'purple' => 'text-purple-600 [&_button]:!text-purple-600 dark:text-purple-200 [&_button]:dark:!text-purple-200 bg-purple-400/20 dark:bg-purple-400/40 [&:is(button)]:hover:bg-purple-400/30 [&:is(button)]:hover:dark:bg-purple-400/50',
        'fuchsia' => 'text-fuchsia-600 [&_button]:!text-fuchsia-600 dark:text-fuchsia-200 [&_button]:dark:!text-fuchsia-200 bg-fuchsia-400/20 dark:bg-fuchsia-400/40 [&:is(button)]:hover:bg-fuchsia-400/30 [&:is(button)]:hover:dark:bg-fuchsia-400/50',
        'pink' => 'text-pink-600 [&_button]:!text-pink-600 dark:text-pink-200 [&_button]:dark:!text-pink-200 bg-pink-400/20 dark:bg-pink-400/40 [&:is(button)]:hover:bg-pink-400/30 [&:is(button)]:hover:dark:bg-pink-400/50',
        'rose' => 'text-rose-600 [&_button]:!text-rose-600 dark:text-rose-200 [&_button]:dark:!text-rose-200 bg-rose-400/20 dark:bg-rose-400/40 [&:is(button)]:hover:bg-rose-400/30 [&:is(button)]:hover:dark:bg-rose-400/50',
    } :  match ($color) {
        'zinc' => 'text-zinc-700 [&_button]:!text-zinc-700 dark:text-zinc-200 [&_button]:dark:!text-zinc-200 bg-zinc-400/15 dark:bg-zinc-400/40 [&:is(button)]:hover:bg-zinc-400/25 [&:is(button)]:hover:dark:bg-zinc-400/50',
        'red' => 'text-red-700 [&_button]:!text-red-700 dark:text-red-200 [&_button]:dark:!text-red-200 bg-red-400/20 dark:bg-red-400/40 [&:is(button)]:hover:bg-red-400/30 [&:is(button)]:hover:dark:bg-red-400/50',
        'orange' => 'text-orange-700 [&_button]:!text-orange-700 dark:text-orange-200 [&_button]:dark:!text-orange-200 bg-orange-400/20 dark:bg-orange-400/40 [&:is(button)]:hover:bg-orange-400/30 [&:is(button)]:hover:dark:bg-orange-400/50',
        'amber' => 'text-amber-700 [&_button]:!text-amber-700 dark:text-amber-200 [&_button]:dark:!text-amber-200 bg-amber-400/25 dark:bg-amber-400/40 [&:is(button)]:hover:bg-amber-400/40 [&:is(button)]:hover:dark:bg-amber-400/50',
        'yellow' => 'text-yellow-800 [&_button]:!text-yellow-800 dark:text-yellow-200 [&_button]:dark:!text-yellow-200 bg-yellow-400/25 dark:bg-yellow-400/40 [&:is(button)]:hover:bg-yellow-400/40 [&:is(button)]:hover:dark:bg-yellow-400/50',
        'lime' => 'text-lime-800 [&_button]:!text-lime-800 dark:text-lime-200 [&_button]:dark:!text-lime-200 bg-lime-400/25 dark:bg-lime-400/40 [&:is(button)]:hover:bg-lime-400/35 [&:is(button)]:hover:dark:bg-lime-400/50',
        'green' => 'text-green-800 [&_button]:!text-green-800 dark:text-green-200 [&_button]:dark:!text-green-200 bg-green-400/20 dark:bg-green-400/40 [&:is(button)]:hover:bg-green-400/30 [&:is(button)]:hover:dark:bg-green-400/50',
        'emerald' => 'text-emerald-800 [&_button]:!text-emerald-800 dark:text-emerald-200 [&_button]:dark:!text-emerald-200 bg-emerald-400/20 dark:bg-emerald-400/40 [&:is(button)]:hover:bg-emerald-400/30 [&:is(button)]:hover:dark:bg-emerald-400/50',
        'teal' => 'text-teal-800 [&_button]:!text-teal-800 dark:text-teal-200 [&_button]:dark:!text-teal-200 bg-teal-400/20 dark:bg-teal-400/40 [&:is(button)]:hover:bg-teal-400/30 [&:is(button)]:hover:dark:bg-teal-400/50',
        'cyan' => 'text-cyan-800 [&_button]:!text-cyan-800 dark:text-cyan-200 [&_button]:dark:!text-cyan-200 bg-cyan-400/20 dark:bg-cyan-400/40 [&:is(button)]:hover:bg-cyan-400/30 [&:is(button)]:hover:dark:bg-cyan-400/50',
        'sky' => 'text-sky-800 [&_button]:!text-sky-800 dark:text-sky-200 [&_button]:dark:!text-sky-200 bg-sky-400/20 dark:bg-sky-400/40 [&:is(button)]:hover:bg-sky-400/30 [&:is(button)]:hover:dark:bg-sky-400/50',
        'blue' => 'text-blue-800 [&_button]:!text-blue-800 dark:text-blue-200 [&_button]:dark:!text-blue-200 bg-blue-400/20 dark:bg-blue-400/40 [&:is(button)]:hover:bg-blue-400/30 [&:is(button)]:hover:dark:bg-blue-400/50',
        'indigo' => 'text-indigo-700 [&_button]:!text-indigo-700 dark:text-indigo-200 [&_button]:dark:!text-indigo-200 bg-indigo-400/20 dark:bg-indigo-400/40 [&:is(button)]:hover:bg-indigo-400/30 [&:is(button)]:hover:dark:bg-indigo-400/50',
        'violet' => 'text-violet-700 [&_button]:!text-violet-700 dark:text-violet-200 [&_button]:dark:!text-violet-200 bg-violet-400/20 dark:bg-violet-400/40 [&:is(button)]:hover:bg-violet-400/30 [&:is(button)]:hover:dark:bg-violet-400/50',
        'purple' => 'text-purple-700 [&_button]:!text-purple-700 dark:text-purple-200 [&_button]:dark:!text-purple-200 bg-purple-400/20 dark:bg-purple-400/40 [&:is(button)]:hover:bg-purple-400/30 [&:is(button)]:hover:dark:bg-purple-400/50',
        'fuchsia' => 'text-fuchsia-700 [&_button]:!text-fuchsia-700 dark:text-fuchsia-200 [&_button]:dark:!text-fuchsia-200 bg-fuchsia-400/20 dark:bg-fuchsia-400/40 [&:is(button)]:hover:bg-fuchsia-400/30 [&:is(button)]:hover:dark:bg-fuchsia-400/50',
        'pink' => 'text-pink-700 [&_button]:!text-pink-700 dark:text-pink-200 [&_button]:dark:!text-pink-200 bg-pink-400/20 dark:bg-pink-400/40 [&:is(button)]:hover:bg-pink-400/30 [&:is(button)]:hover:dark:bg-pink-400/50',
        'rose' => 'text-rose-700 [&_button]:!text-rose-700 dark:text-rose-200 [&_button]:dark:!text-rose-200 bg-rose-400/20 dark:bg-rose-400/40 [&:is(button)]:hover:bg-rose-400/30 [&:is(button)]:hover:dark:bg-rose-400/50',
    });
@endphp

<?php switch ($variant): case ('outline'): ?>
<svg {{ $attributes->class($classes) }} data-flux-icon xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 36 36" stroke="currentColor" aria-hidden="true" data-slot="icon">
    @if ($image)
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
    @else
        <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="currentColor" stroke="none" class="font-400 text-sm select-none">{{ $name }}</text>
    @endif 
</svg>

        <?php break; ?>

    <?php case ('solid'): ?>
<svg {{ $attributes->class($classes) }} data-flux-icon xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36" fill="currentColor" aria-hidden="true" data-slot="icon">
    @if($image)
        <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd"/>
    @else
        <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" class="font-500 text-sm text-center select-none">{{ $name }}</text>
    @endif
</svg>                         

        <?php break; ?>

    <?php case ('mini'): ?>
<svg {{ $attributes->class($classes) }} data-flux-icon xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" data-slot="icon">
    @if($image)
        <path d="M10 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.465 14.493a1.23 1.23 0 0 0 .41 1.412A9.957 9.957 0 0 0 10 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 0 0-13.074.003Z"/>
    @else
        <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" class="font-200 text-sm text-center select-none">{{ $name }}</text>
    @endif
</svg>
        <?php break; ?>

    <?php case ('micro'): ?>
<svg {{ $attributes->class($classes) }} data-flux-icon xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
    @if($image)
        <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM12.735 14c.618 0 1.093-.561.872-1.139a6.002 6.002 0 0 0-11.215 0c-.22.578.254 1.139.872 1.139h9.47Z"/>
    @else
        <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" class="font-200 text-xs text-center select-none">{{ $name }}</text>
    @endif
</svg>

        <?php break; ?>

<?php endswitch; ?>