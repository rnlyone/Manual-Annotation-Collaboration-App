@php
	$isSessionReview = $isSessionReview ?? false;
	$reviewItems = collect($reviewItems ?? []);
@endphp

@push('styles')
<style>
	.annotation-layout .card {
		background: #1c1b2e;
		border: 1px solid rgba(255, 255, 255, 0.05);
		box-shadow: 0 20px 60px rgba(5, 3, 27, 0.35);
	}

	.annotation-text-card .card-header {
		border-bottom: 1px solid rgba(255, 255, 255, 0.05);
	}

	.annotation-text {
		font-size: 1.05rem;
		line-height: 1.8;
		white-space: pre-wrap;
		font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
		color: #f5f4ff;
	}

	.annotation-text-container {
		background: #15132b;
		border-radius: 1rem;
		padding: 1.5rem;
		border: 1px solid rgba(255, 255, 255, 0.04);
		min-height: 240px;
	}

	.annotation-empty {
		text-align: center;
		padding: 2rem 1rem;
		color: #8f8b9a;
	}

	.category-multiselect {
		min-height: 18rem;
		background: #14122a;
		border-radius: 0.85rem;
		border: 1px solid rgba(154, 150, 194, 0.45);
		color: #e2e0ff;
		box-shadow: inset 0 -6px 18px rgba(4, 1, 27, 0.45);
	}

	.category-multiselect option {
		padding: 0.65rem 0.75rem;
		border-bottom: 1px solid rgba(255, 255, 255, 0.02);
	}

	.category-multiselect option:checked {
		background: linear-gradient(135deg, #6f66f1, #9b88ff);
		color: #fff;
	}

	.annotation-controls {
		border-top: 1px solid rgba(255, 255, 255, 0.08);
		margin-top: 1.5rem;
		padding-top: 1.25rem;
	}

	.annotation-controls .btn {
		min-width: 120px;
	}

	.annotation-status {
		color: #a5a2c7;
	}

	.annotation-map-card .map-node-btn {
		min-width: 2.5rem;
		min-height: 2.5rem;
		font-weight: 600;
		border-width: 2px;
		border-radius: 0.55rem;
	}

	.annotation-map-card .map-node-btn.active {
		color: #fff;
	}

	.annotation-map-grid {
		display: flex;
		flex-wrap: wrap;
		gap: 0.5rem;
	}

	.annotation-map-empty {
		color: #a5a2c7;
	}
</style>
@endpush

<div class="container-xxl flex-grow-1 container-p-y">
	<div class="row mb-4">
		<div class="col-12">
			<nav aria-label="breadcrumb" class="d-flex align-items-center gap-2">
				<ol class="breadcrumb breadcrumb-style1 mb-0">
					<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
					<li class="breadcrumb-item"><a href="{{ route('annotations.index') }}">Annotations</a></li>
					@if($isSessionReview)
						<li class="breadcrumb-item"><a href="{{ route('session-logs.index') }}">Session Logs</a></li>
						<li class="breadcrumb-item active" aria-current="page">Review Session</li>
					@else
						<li class="breadcrumb-item active" aria-current="page">Annotate</li>
					@endif
				</ol>
			</nav>
		</div>
	</div>

	<div class="row g-4 annotation-layout">
		<div class="col-12 col-xl-8">
			<div class="card annotation-text-card h-100">
				<div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
					<div>
						<h4 class="mb-1 text-white">{{ $workbenchPackage->name ?? 'Annotation Workbench' }}</h4>
						<small class="text-muted">
							{{ $isSessionReview ? 'Review and adjust your previous annotations' : 'Assign categories while keeping the text in focus' }}
						</small>
					</div>
					<div class="text-end">
						<span class="badge bg-label-primary"><i class="bx bx-package me-1"></i>Package #{{ $workbenchPackage->id ?? '-' }}</span>
						@if($isSessionReview)
							<div><span class="badge bg-label-info mt-2">Session Review Mode</span></div>
						@endif
						<div class="small annotation-status" id="workItemMeta"></div>
					</div>
				</div>
				<div class="card-body">
					<div class="annotation-text-container">
						<div class="annotation-text" id="workItemContent">Loading next entry…</div>
						<div class="annotation-empty d-none" id="emptyState">
							<i class="bx bx-party me-1"></i>You are fully caught up! Nothing left to annotate in this package.
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-xl-4">
			<div class="card h-100">
				<div class="card-header d-flex justify-content-between align-items-start gap-2 flex-wrap">
					<div>
						<h6 class="mb-0 text-white">Categories</h6>
						<small class="annotation-status" id="saveStatus">Waiting for selection…</small>
						@if(isset($sessionLog))
							<div class="small text-muted">
								Session #{{ $sessionLog->id }} · {{ optional($sessionLog->created_at)->diffForHumans() }}
								@if($isSessionReview && $sessionLog->ended_at)
									<br><span class="text-warning">Ended {{ optional($sessionLog->ended_at)->diffForHumans() }}</span>
								@endif
							</div>
						@endif
					</div>
					<div class="d-flex gap-2">
						@if($isSessionReview)
							<a href="{{ route('session-logs.index') }}" class="btn btn-sm btn-label-secondary" id="closeReviewButton">
								<i class="bx bx-x me-1"></i> Close Review
							</a>
						@elseif(isset($sessionLog))
							<button class="btn btn-sm btn-danger" id="endSessionButton">
								<i class="bx bx-power-off me-1"></i> End Session
							</button>
						@endif
					</div>
				</div>
				<div class="card-body d-flex flex-column h-100">
					<label for="categorySelect" class="form-label text-muted">Hold Cmd/Ctrl to select multiple categories</label>
					<select id="categorySelect" class="form-select form-select-lg category-multiselect" multiple size="10">
						@foreach($categories as $category)
							<option value="{{ $category->id }}">{{ $category->name }}</option>
						@endforeach
					</select>

					<div class="annotation-controls d-flex flex-column gap-3 mt-auto">
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox" id="autoAdvanceToggle" checked>
							<label class="form-check-label" for="autoAdvanceToggle">Automatically next after annotation</label>
						</div>
						<div class="d-flex justify-content-end">
							<button class="btn btn-label-primary" id="nextItemButton">
								Next <i class="bx bx-chevron-right ms-1"></i>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	@unless($isSessionReview)
		<div class="row mt-4">
			<div class="col-12">
				<div class="card annotation-map-card" id="annotationMapCard">
					<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
						<div>
							<h6 class="mb-1 text-white">Annotation Map</h6>
							<small class="annotation-status" id="annotationMapStatus">Annotate items to build the map</small>
						</div>
						<div class="d-flex gap-2">
							<button class="btn btn-sm btn-outline-secondary" id="annotationMapNext" type="button" disabled>
								Next
							</button>
							<button class="btn btn-sm btn-label-primary" id="annotationMapJump" type="button">
								Jump
							</button>
						</div>
					</div>
					<div class="card-body">
						<div class="annotation-map-grid" id="annotationMapNodes"></div>
						<div class="annotation-map-empty mt-2" id="annotationMapEmpty">No annotations in this session yet.</div>
					</div>
				</div>
			</div>
		</div>
	@endunless
</div>

@push('scripts')
<script>
	window.annotationWorkbenchConfig = {
		packageId: {{ $workbenchPackage->id ?? 'null' }},
		fetchUrl: "{{ (! $isSessionReview && isset($workbenchPackage)) ? route('packages.annotations.workItem', $workbenchPackage) : '' }}",
		saveUrl: "{{ isset($workbenchPackage) ? route('packages.annotations.save', $workbenchPackage) : '' }}",
		mapUrl: "{{ (! $isSessionReview && isset($workbenchPackage)) ? route('packages.annotations.sessionMap', $workbenchPackage) : '' }}",
		initialItem: @json($isSessionReview ? $reviewItems->first() : $initialWorkItem),
		reviewMode: {{ $isSessionReview ? 'true' : 'false' }},
		reviewItems: @json($reviewItems->values()->all()),
		autoAdvance: true,
	};

	$(function () {
		const config = window.annotationWorkbenchConfig;
		const $categorySelect = $('#categorySelect');
		const $content = $('#workItemContent');
		const $saveStatus = $('#saveStatus');
		const $emptyState = $('#emptyState');
		const $meta = $('#workItemMeta');
		const $nextButton = $('#nextItemButton');
		const $autoAdvance = $('#autoAdvanceToggle');
		const $endSessionButton = $('#endSessionButton');
		const $mapNodes = $('#annotationMapNodes');
		const $mapEmpty = $('#annotationMapEmpty');
		const $mapStatus = $('#annotationMapStatus');
		const $mapNextButton = $('#annotationMapNext');
		const $mapJumpButton = $('#annotationMapJump');

		const reviewMode = Boolean(config.reviewMode);
		const reviewItems = Array.isArray(config.reviewItems) ? config.reviewItems : [];
		let reviewIndex = 0;

		let currentItem = reviewMode
			? (reviewItems.length ? reviewItems[0] : null)
			: (config.initialItem || null);
		let isSaving = false;
		let isLoading = false;
		let suppressChange = false;
		const mapState = {
			items: [],
			activeIndex: null,
		};
		const mapEnabled = !reviewMode && Boolean(config.mapUrl);
		let lastQueuePivotId = currentItem && currentItem.pivot_id ? currentItem.pivot_id : null;
        let warnOnLeave = !reviewMode;

		const warnMessage = 'You have an active annotation session. Are you sure you want to leave this page?';
		const beforeUnloadHandler = (event) => {
			if (!warnOnLeave) {
				return;
			}
			event.preventDefault();
			event.returnValue = warnMessage;
			return warnMessage;
		};

		const disableLeaveWarning = () => {
			warnOnLeave = false;
			window.removeEventListener('beforeunload', beforeUnloadHandler);
		};

		if (!reviewMode) {
			window.addEventListener('beforeunload', beforeUnloadHandler);
		}

		const updateReviewNavState = () => {
			if (!reviewMode) {
				return;
			}
			const hasItems = reviewItems.length > 0;
			$nextButton.prop('disabled', !hasItems || reviewIndex >= reviewItems.length - 1);
		};

		const setStatus = (message, state = 'idle') => {
			const icon = state === 'saving'
				? '<span class="spinner-border spinner-border-sm align-middle me-1"></span>'
				: state === 'error'
					? '<i class="bx bx-error-circle text-danger me-1"></i>'
					: '<i class="bx bx-check-circle text-success me-1"></i>';
			$saveStatus.html(message ? icon + message : '');
		};

		const renderEmptyState = (visible) => {
			if (visible) {
				$emptyState.removeClass('d-none');
				$content.closest('.annotation-text-container').addClass('bg-light');
				$content.addClass('text-muted').text('');
				$categorySelect.prop('disabled', true).val([]);
			} else {
				$emptyState.addClass('d-none');
				$content.closest('.annotation-text-container').removeClass('bg-light');
				$content.removeClass('text-muted');
				$categorySelect.prop('disabled', false);
			}
		};

		const renderWorkItem = (item) => {
			currentItem = item;
			if (!item) {
				renderEmptyState(true);
				$meta.text('');
				suppressChange = true;
				$categorySelect.val([]);
				suppressChange = false;
				lastQueuePivotId = null;
				updateReviewNavState();
				return;
			}

			renderEmptyState(false);
			const paragraphs = (item.content || '').trim();
			$content.html(paragraphs ? paragraphs.replace(/\n/g, '<br>') : '<em class="text-muted">No content provided.</em>');
			$meta.text(`Data #${item.data_id}`);
			suppressChange = true;
			$categorySelect.val((item.category_ids || []).map(String));
			suppressChange = false;
			if (item.pivot_id) {
				lastQueuePivotId = item.pivot_id;
			}
			updateReviewNavState();
		};

		const toCategoryArray = (value) => Array.isArray(value) ? value.map(String) : [];

		const refreshMapStatusText = () => {
			if (!mapEnabled) {
				return;
			}

			if (!mapState.items.length) {
				$mapStatus.text('Annotate items to build the map');
				return;
			}

			if (mapState.activeIndex === null) {
				$mapStatus.text(`${mapState.items.length} annotated in this session`);
				return;
			}

			const activeItem = mapState.items[mapState.activeIndex];
			$mapStatus.text(`Annotation #${activeItem.number} selected (${mapState.items.length} total)`);
		};

		const updateMapControls = () => {
			if (!mapEnabled) {
				return;
			}
			const hasItems = mapState.items.length > 0;
			const canAdvance = hasItems && (mapState.activeIndex === null || mapState.activeIndex < mapState.items.length - 1);
			$mapNextButton.prop('disabled', !canAdvance);
			$mapJumpButton.prop('disabled', !config.fetchUrl);
		};

		const renderAnnotationMap = () => {
			if (!mapEnabled) {
				return;
			}

			$mapNodes.empty();
			if (!mapState.items.length) {
				$mapEmpty.removeClass('d-none');
			} else {
				$mapEmpty.addClass('d-none');
			}

			mapState.items.forEach((item, index) => {
				const isActive = mapState.activeIndex === index;
				const classes = isActive
					? 'btn btn-sm btn-primary map-node-btn active text-white'
					: 'btn btn-sm btn-outline-primary map-node-btn';
				const $btn = $('<button type="button"></button>')
					.addClass(classes)
					.attr('data-map-index', index)
					.text(item.number);
				$mapNodes.append($btn);
			});

			updateMapControls();
			refreshMapStatusText();
		};

		const setActiveMapIndex = (index) => {
			if (!mapEnabled) {
				return;
			}

			if (index === null || index < 0 || index >= mapState.items.length) {
				mapState.activeIndex = null;
				renderAnnotationMap();
				return;
			}

			mapState.activeIndex = index;
			const item = mapState.items[index];
			renderAnnotationMap();
			if (item) {
				renderWorkItem({
					...item,
					category_ids: toCategoryArray(item.category_ids),
				});
			}
		};

		const upsertSessionMapItem = (payload) => {
			if (!mapEnabled || !payload || !payload.annotation_id) {
				return;
			}

			const normalized = {
				annotation_id: payload.annotation_id,
				data_id: payload.data_id,
				content: payload.content,
				category_ids: toCategoryArray(payload.category_ids),
			};

			const existingIndex = mapState.items.findIndex(item => item.annotation_id === normalized.annotation_id);
			if (existingIndex >= 0) {
				mapState.items[existingIndex] = {
					...mapState.items[existingIndex],
					...normalized,
				};
			} else {
				normalized.number = mapState.items.length + 1;
				mapState.items.push(normalized);
			}

			renderAnnotationMap();
		};

		const loadSessionMap = () => {
			if (!mapEnabled || !config.mapUrl) {
				return;
			}

			$.getJSON(config.mapUrl)
				.done((response) => {
					const items = Array.isArray(response.items)
						? response.items.map(item => ({
							number: item.number,
							annotation_id: item.annotation_id,
							data_id: item.data_id,
							content: item.content,
							category_ids: toCategoryArray(item.category_ids),
						}))
						: [];
					mapState.items = items;
					mapState.activeIndex = null;
					renderAnnotationMap();
				})
				.fail(() => {
					$mapStatus.text('Unable to load annotation map');
				});
		};

		const handleMapNodeClick = (event) => {
			const index = parseInt($(event.currentTarget).attr('data-map-index'), 10);
			if (Number.isNaN(index)) {
				return;
			}
			setActiveMapIndex(index);
		};

		const handleMapNext = () => {
			if (!mapEnabled || !mapState.items.length) {
				return;
			}
			const nextIndex = mapState.activeIndex === null
				? 0
				: Math.min(mapState.activeIndex + 1, mapState.items.length - 1);
			setActiveMapIndex(nextIndex);
		};

		const handleMapJump = () => {
			if (!mapEnabled || !config.fetchUrl) {
				return;
			}
			mapState.activeIndex = null;
			renderAnnotationMap();
			fetchWorkItem('next');
		};

		const toggleControls = (disabled) => {
			if (reviewMode) {
				$categorySelect.prop('disabled', disabled || !currentItem);
				if (disabled) {
					$nextButton.prop('disabled', true);
				} else {
					updateReviewNavState();
				}
				return;
			}

			$nextButton.prop('disabled', disabled);
			$categorySelect.prop('disabled', disabled || !currentItem);
		};

		const fetchWorkItem = (direction) => {
			if (reviewMode) {
				if (!reviewItems.length) {
					renderWorkItem(null);
					return;
				}

				if (direction === 'next' && reviewIndex < reviewItems.length - 1) {
					reviewIndex += 1;
				} else if (direction === 'prev' && reviewIndex > 0) {
					reviewIndex -= 1;
				}

				renderWorkItem(reviewItems[reviewIndex]);
				return;
			}

			if (!config.fetchUrl || isLoading) {
				return;
			}
			isLoading = true;
			toggleControls(true);
			$saveStatus.html('<span class="spinner-border spinner-border-sm align-middle me-1"></span>Loading…');

			const cursorValue = lastQueuePivotId;
			$.get(config.fetchUrl, {
				cursor: cursorValue,
				direction,
			})
				.done((response) => {
					const item = response.data || null;
					if (item && item.pivot_id) {
						lastQueuePivotId = item.pivot_id;
					} else if (!item) {
						lastQueuePivotId = null;
					}
					renderWorkItem(item);
					setStatus('Ready', 'idle');
				})
				.fail(() => {
					setStatus('Unable to load item', 'error');
				})
				.always(() => {
					isLoading = false;
					toggleControls(false);
				});
		};

		const saveSelection = () => {
			if (!config.saveUrl || !currentItem || isSaving) {
				return;
			}

			const payload = $categorySelect.val() || [];
			isSaving = true;
			setStatus('Saving…', 'saving');

			$.ajax({
				url: config.saveUrl,
				method: 'POST',
				data: {
					_token: '{{ csrf_token() }}',
					data_id: currentItem.data_id,
					category_ids: payload,
				},
			})
				.done((response) => {
					setStatus('Saved', 'success');
					if (mapEnabled && payload.length && response && response.annotation_id) {
						upsertSessionMapItem({
							annotation_id: response.annotation_id,
							data_id: currentItem?.data_id,
							content: currentItem?.content,
							category_ids: payload,
						});
					}
					if ($autoAdvance.is(':checked') && payload.length > 0) {
						fetchWorkItem('next');
					} else if (reviewMode) {
						updateReviewNavState();
					}
				})
				.fail((xhr) => {
					const message = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save annotation';
					setStatus(message, 'error');
				})
				.always(() => {
					isSaving = false;
				});
		};

		$categorySelect.on('change', function () {
			if (suppressChange) {
				return;
			}
			saveSelection();
		});

		$nextButton.on('click', function () {
			if (mapEnabled) {
				setActiveMapIndex(null);
			}
			fetchWorkItem('next');
		});

		if (mapEnabled) {
			$mapNodes.on('click', '.map-node-btn', handleMapNodeClick);
			$mapNextButton.on('click', handleMapNext);
			$mapJumpButton.on('click', handleMapJump);
			loadSessionMap();
		}

		if ($endSessionButton.length) {
			$endSessionButton.on('click', function () {
				if (!confirm('End current annotation session?')) {
					return;
				}

				$endSessionButton.prop('disabled', true);

				$.ajax({
					url: '{{ route('session-logs.end') }}',
					type: 'POST',
					data: {
						_token: '{{ csrf_token() }}',
					},
				})
					.done(() => {
						disableLeaveWarning();
						window.location.href = '{{ route('annotations.index') }}';
					})
					.fail(() => {
						setStatus('Unable to end session', 'error');
					})
					.always(() => {
						$endSessionButton.prop('disabled', false);
					});
			});
		}

		renderWorkItem(currentItem);
		setStatus('Ready', 'idle');
		toggleControls(false);
	});
</script>
@endpush
