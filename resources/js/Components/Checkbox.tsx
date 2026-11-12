import { InputHTMLAttributes } from 'react';

export default function Checkbox({
    className = '',
    ...props
}: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-[#0F172A] text-blue-600 shadow-2xs focus:ring-blue-500 dark:focus:ring-offset-slate-900 ' +
                className
            }
        />
    );
}
