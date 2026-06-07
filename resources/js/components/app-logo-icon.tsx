import type { SVGAttributes } from 'react';

/**
 * OpenCookie brand mark: a bitten cookie whose chips double as the nodes of a
 * small network — tying together "cookie" and "web". Monochrome, driven by
 * `currentColor`, so it inherits text color anywhere it is used.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 32 32"
            xmlns="http://www.w3.org/2000/svg"
            fill="currentColor"
            {...props}
        >
            <mask id="opencookie-mark">
                <rect width="32" height="32" fill="black" />
                {/* cookie body */}
                <circle cx="16" cy="16" r="14" fill="white" />
                {/* bite taken out of the top-right */}
                <circle cx="30" cy="5" r="8" fill="black" />
                {/* network links between chips */}
                <g
                    stroke="black"
                    strokeWidth="1.1"
                    strokeLinecap="round"
                    fill="none"
                >
                    <path d="M11 12 L16 16 L20.5 10.5" />
                    <path d="M16 16 L21 19.5" />
                    <path d="M16 16 L12.5 21" />
                </g>
                {/* chips / nodes (holes) */}
                <circle cx="11" cy="12" r="1.8" fill="black" />
                <circle cx="20.5" cy="10.5" r="1.5" fill="black" />
                <circle cx="16" cy="16" r="1.5" fill="black" />
                <circle cx="21" cy="19.5" r="1.7" fill="black" />
                <circle cx="12.5" cy="21" r="1.9" fill="black" />
            </mask>
            <rect width="32" height="32" mask="url(#opencookie-mark)" />
        </svg>
    );
}
