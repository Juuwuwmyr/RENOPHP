<?php

namespace Horizon\View\Compilers;

/**
 * BladeCompiler
 * 
 * Compiles Blade-like template syntax into native PHP.
 * Provides elegant syntax while maintaining clarity.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Compiles to readable PHP code
 * - No runtime eval()
 * - Easy to debug
 * - Explicit escaping
 * 
 * Supported Directives:
 * - {{ $var }} - Escaped output
 * - {!! $var !!} - Raw output
 * - @if, @else, @elseif, @endif
 * - @foreach, @endforeach
 * - @for, @endfor
 * - @while, @endwhile
 * - @isset, @endisset
 * - @empty, @endempty
 * - @include
 * - @extends, @section, @yield
 * - @csrf, @method
 * - And more...
 */
class BladeCompiler extends Compiler
{
    /**
     * Compile the given template string
     *
     * @param string $value
     * @return string
     */
    protected function compileString(string $value): string
    {
        // Compile all directives
        $value = $this->compileComments($value);
        $value = $this->compileExtends($value);
        $value = $this->compileLayouts($value);
        $value = $this->compileComponents($value);
        $value = $this->compileConditionals($value);
        $value = $this->compileLoops($value);
        $value = $this->compileIncludes($value);
        $value = $this->compileHelpers($value);
        $value = $this->compileEchos($value);
        $value = $this->compilePhp($value);

        return $value;
    }

    /**
     * Compile Blade comments into valid PHP
     *
     * @param string $value
     * @return string
     */
    protected function compileComments(string $value): string
    {
        return preg_replace('/\{\{--(.*?)--\}\}/s', '<?php /* $1 */ ?>', $value);
    }

    /**
     * Compile template extensions
     *
     * @param string $value
     * @return string
     */
    protected function compileExtends(string $value): string
    {
        $pattern = '/^@extends\(\s*[\'"](.+?)[\'"]\s*\)/m';
        return preg_replace($pattern, '<?php extend(\'$1\'); ?>', $value);
    }

    /**
     * Compile layout directives
     *
     * @param string $value
     * @return string
     */
    protected function compileLayouts(string $value): string
    {
        // @section
        $value = preg_replace('/@section\(\s*[\'"](.+?)[\'"]\s*\)/', '<?php section(\'$1\'); ?>', $value);
        
        // @endsection
        $value = preg_replace('/@endsection/', '<?php endsection(); ?>', $value);
        
        // @show
        $value = preg_replace('/@show/', '<?php echo show(); ?>', $value);
        
        // @yield
        $value = preg_replace('/@yield\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.+?)[\'"]\s*)?\)/', '<?php echo yield_content(\'$1\', \'$2\'); ?>', $value);
        
        // @parent
        $value = preg_replace('/@parent/', '@parent', $value);
        
        // @push
        $value = preg_replace('/@push\(\s*[\'"](.+?)[\'"]\s*\)/', '<?php push(\'$1\'); ?>', $value);
        
        // @endpush
        $value = preg_replace('/@endpush/', '<?php endpush(); ?>', $value);
        
        // @prepend
        $value = preg_replace('/@prepend\(\s*[\'"](.+?)[\'"]\s*\)/', '<?php prepend(\'$1\'); ?>', $value);
        
        // @endprepend
        $value = preg_replace('/@endprepend/', '<?php endprepend(); ?>', $value);
        
        // @stack
        $value = preg_replace('/@stack\(\s*[\'"](.+?)[\'"]\s*\)/', '<?php echo stack(\'$1\'); ?>', $value);

        return $value;
    }

    /**
     * Compile component directives
     *
     * @param string $value
     * @return string
     */
    protected function compileComponents(string $value): string
    {
        // @component
        $value = preg_replace('/@component\(\s*(.+?)\s*(?:,\s*(.+?)\s*)?\)/', '<?php component($1, $2 ?? []); ?>', $value);
        
        // @endcomponent
        $value = preg_replace('/@endcomponent/', '<?php echo endcomponent(); ?>', $value);
        
        // @slot
        $value = preg_replace('/@slot\(\s*[\'"](.+?)[\'"]\s*\)/', '<?php slot(\'$1\'); ?>', $value);
        
        // @endslot
        $value = preg_replace('/@endslot/', '<?php endslot(); ?>', $value);

        return $value;
    }

    /**
     * Compile conditional directives
     *
     * @param string $value
     * @return string
     */
    protected function compileConditionals(string $value): string
    {
        // @if
        $value = preg_replace('/@if\(\s*(.+?)\s*\)/', '<?php if($1): ?>', $value);
        
        // @elseif
        $value = preg_replace('/@elseif\(\s*(.+?)\s*\)/', '<?php elseif($1): ?>', $value);
        
        // @else
        $value = preg_replace('/@else/', '<?php else: ?>', $value);
        
        // @endif
        $value = preg_replace('/@endif/', '<?php endif; ?>', $value);
        
        // @unless
        $value = preg_replace('/@unless\(\s*(.+?)\s*\)/', '<?php if(!($1)): ?>', $value);
        
        // @endunless
        $value = preg_replace('/@endunless/', '<?php endif; ?>', $value);
        
        // @isset
        $value = preg_replace('/@isset\(\s*(.+?)\s*\)/', '<?php if(isset($1)): ?>', $value);
        
        // @endisset
        $value = preg_replace('/@endisset/', '<?php endif; ?>', $value);
        
        // @empty
        $value = preg_replace('/@empty\(\s*(.+?)\s*\)/', '<?php if(empty($1)): ?>', $value);
        
        // @endempty
        $value = preg_replace('/@endempty/', '<?php endif; ?>', $value);

        return $value;
    }

    /**
     * Compile loop directives
     *
     * @param string $value
     * @return string
     */
    protected function compileLoops(string $value): string
    {
        // @foreach
        $value = preg_replace('/@foreach\(\s*(.+?)\s*\)/', '<?php foreach($1): ?>', $value);
        
        // @endforeach
        $value = preg_replace('/@endforeach/', '<?php endforeach; ?>', $value);
        
        // @for
        $value = preg_replace('/@for\(\s*(.+?)\s*\)/', '<?php for($1): ?>', $value);
        
        // @endfor
        $value = preg_replace('/@endfor/', '<?php endfor; ?>', $value);
        
        // @while
        $value = preg_replace('/@while\(\s*(.+?)\s*\)/', '<?php while($1): ?>', $value);
        
        // @endwhile
        $value = preg_replace('/@endwhile/', '<?php endwhile; ?>', $value);
        
        // @continue
        $value = preg_replace('/@continue(\(\s*(.+?)\s*\))?/', '<?php if($2) continue; ?>', $value);
        
        // @break
        $value = preg_replace('/@break(\(\s*(.+?)\s*\))?/', '<?php if($2) break; ?>', $value);

        return $value;
    }

    /**
     * Compile include directives
     *
     * @param string $value
     * @return string
     */
    protected function compileIncludes(string $value): string
    {
        // @include
        $value = preg_replace('/@include\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?)\s*)?\)/', '<?php echo view(\'$1\', $2 ?? [])->render(); ?>', $value);
        
        // @includeIf
        $value = preg_replace('/@includeIf\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?)\s*)?\)/', '<?php if(view()->exists(\'$1\')) echo view(\'$1\', $2 ?? [])->render(); ?>', $value);
        
        // @includeWhen
        $value = preg_replace('/@includeWhen\(\s*(.+?)\s*,\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?)\s*)?\)/', '<?php if($1) echo view(\'$2\', $3 ?? [])->render(); ?>', $value);

        return $value;
    }

    /**
     * Compile helper directives
     *
     * @param string $value
     * @return string
     */
    protected function compileHelpers(string $value): string
    {
        // @csrf
        $value = preg_replace('/@csrf/', '<?php echo csrf_field(); ?>', $value);
        
        // @method
        $value = preg_replace('/@method\(\s*[\'"](.+?)[\'"]\s*\)/', '<?php echo method_field(\'$1\'); ?>', $value);
        
        // @json
        $value = preg_replace('/@json\(\s*(.+?)\s*\)/', '<?php echo json_encode_safe($1); ?>', $value);
        
        // @dd (dump and die)
        $value = preg_replace('/@dd\(\s*(.+?)\s*\)/', '<?php dd($1); ?>', $value);
        
        // @dump
        $value = preg_replace('/@dump\(\s*(.+?)\s*\)/', '<?php dump($1); ?>', $value);

        return $value;
    }

    /**
     * Compile echo statements
     *
     * @param string $value
     * @return string
     */
    protected function compileEchos(string $value): string
    {
        // Compile escaped echoes {{ }}
        $value = preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?php echo e($1); ?>', $value);
        
        // Compile raw echoes {!! !!}
        $value = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?php echo raw($1); ?>', $value);

        return $value;
    }

    /**
     * Compile PHP directives
     *
     * @param string $value
     * @return string
     */
    protected function compilePhp(string $value): string
    {
        // @php
        $value = preg_replace('/@php/', '<?php', $value);
        
        // @endphp
        $value = preg_replace('/@endphp/', '?>', $value);

        return $value;
    }
}
