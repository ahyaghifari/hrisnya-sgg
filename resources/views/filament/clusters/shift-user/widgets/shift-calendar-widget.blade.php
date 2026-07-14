@php
    use Filament\Support\Facades\FilamentAsset;
    use Guava\Calendar\Enums\Context;
    use Filament\Support\Facades\FilamentColor;
    use Filament\Support\View\Components\ButtonComponent;
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        :after-header="$this->getCachedHeaderActionsComponent()"
        :footer="$this->getCachedFooterActionsComponent()"
    >
        <style>
            .ec-event.ec-preview,
            .ec-now-indicator {
                z-index: 30;
            }

            /* Stack toolbar on small screens */
            @media (max-width: 480px) {
                .ec-toolbar {
                    flex-wrap: wrap;
                    gap: 0.4rem;
                }
                .ec-toolbar .ec-toolbar-section {
                    width: 100%;
                    justify-content: center;
                }
                .ec-button-group {
                    flex-wrap: wrap;
                    justify-content: center;
                }
            }
        </style>

        @if($heading = $this->getHeading())
            <x-slot name="heading">
                {{ $this->getHeading() }}
            </x-slot>
        @endif

        {{-- Responsive wrapper: dispatches Livewire calendar--set on screen resize --}}
        <div
            x-data="{}"
            x-init="
                const desktopView = @js($this->getCalendarView()->value);
                const mobileView  = 'listMonth';
                let currentBp = window.innerWidth >= 768 ? 'desktop' : 'mobile';

                const onResize = () => {
                    const bp = window.innerWidth >= 768 ? 'desktop' : 'mobile';
                    if (bp !== currentBp) {
                        currentBp = bp;
                        $wire.dispatch('calendar--set', { key: 'view', value: bp === 'desktop' ? desktopView : mobileView });
                    }
                };

                window.addEventListener('resize', onResize);
                $cleanup(() => window.removeEventListener('resize', onResize));
            "
        >
            <div
                wire:ignore
                x-load
                x-load-src="{{ FilamentAsset::getAlpineComponentSrc('calendar', 'guava/calendar') }}"
                x-data="calendar({
                    view: window.innerWidth < 768 ? 'listMonth' : @js($this->getCalendarView()),
                    locale: @js($this->getLocale()),
                    firstDay: @js($this->getFirstDay()),
                    dayMaxEvents: @js($this->getDayMaxEvents()),
                    eventContent: @js($this->getEventContentJs()),
                    eventClickEnabled: @js($this->isEventClickEnabled()),
                    eventDragEnabled: @js($this->isEventDragEnabled()),
                    eventResizeEnabled: @js($this->isEventResizeEnabled()),
                    noEventsClickEnabled: @js($this->isNoEventsClickEnabled()),
                    dateClickEnabled: @js($this->isDateClickEnabled()),
                    dateSelectEnabled: @js($this->isDateSelectEnabled()),
                    datesSetEnabled: @js($this->isDatesSetEnabled()),
                    viewDidMountEnabled: @js($this->isViewDidMountEnabled()),
                    eventAllUpdatedEnabled: @js($this->isEventAllUpdatedEnabled()),
                    hasDateClickContextMenu: @js($this->hasContextMenu(Context::DateClick)),
                    hasDateSelectContextMenu: @js($this->hasContextMenu(Context::DateSelect)),
                    hasEventClickContextMenu: @js($this->hasContextMenu(Context::EventClick)),
                    hasNoEventsClickContextMenu: @js($this->hasContextMenu(Context::NoEventsClick)),
                    resources: @js($this->getResourcesJs()),
                    resourceLabelContent: @js($this->getResourceLabelContentJs()),
                    theme: @js($this->getTheme()),
                    options: @js($this->getOptions()),
                    eventAssetUrl: @js(FilamentAsset::getAlpineComponentSrc('calendar-event', 'guava/calendar')),
                })"
                @class(FilamentColor::getComponentClasses(ButtonComponent::class, 'primary'))
            >
                <div data-calendar></div>
                @if($this->hasContextMenu())
                    <x-guava-calendar::context-menu/>
                @endif
            </div>
        </div>
    </x-filament::section>

    <x-filament-actions::modals/>
</x-filament-widgets::widget>
