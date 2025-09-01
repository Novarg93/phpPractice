import type { Product } from '@/types'

export type ProductWithGroups = Product & {
  option_groups?: Array<ChoiceGroup | QtyGroup | DoubleRangeGroup>
}

export type GroupKind =
  | 'radio_additive'
  | 'checkbox_additive'
  | 'radio_percent'
  | 'checkbox_percent'
  | 'quantity_slider'
  | 'double_range_slider'

export type ChoiceKind =
  | 'radio_additive'
  | 'checkbox_additive'
  | 'radio_percent'
  | 'checkbox_percent'

export type Currency = 'USD' | 'EUR' | 'GBP' | string

export interface OptionItem {
  id: number
  title: string
  price_delta_cents?: number | null
  value_percent?: number | null
  is_default?: boolean
  is_active?: boolean
}

interface BaseGroup {
  id: number
  title: string
  is_required?: boolean
}

export interface ChoiceGroup extends BaseGroup {
  type: ChoiceKind
  multiply_by_qty?: boolean
  values: OptionItem[]
}

export interface QtyGroup extends BaseGroup {
  type: 'quantity_slider'
  qty_min: number | null
  qty_max: number | null
  qty_step: number | null
  qty_default: number | null
}

export interface RangeTier {
  from: number
  to: number
  unit_price_cents: number
  label?: string
  min_block?: number
  multiplier?: number
  cap_cents?: number
}

export interface DoubleRangeGroup extends BaseGroup {
  type: 'double_range_slider'
  slider_min: number
  slider_max: number
  slider_step: number
  range_default_min: number | null
  range_default_max: number | null
  pricing_mode: 'flat' | 'tiered'
  unit_price_cents?: number
  base_fee_cents?: number
  max_span?: number | null
  tier_combine_strategy?: 'sum_piecewise' | 'highest_tier_only' | 'weighted_average'
  tiers?: RangeTier[]
}

export type AnyGroup = ChoiceGroup | QtyGroup | DoubleRangeGroup

// общее состояние выбора одной группы
export type Selection =
  | number            // radio id
  | number[]          // checkbox ids
  | { min: number; max: number } // range
  | null              // пусто
  | number            // qty value (для удобства — то же число)

