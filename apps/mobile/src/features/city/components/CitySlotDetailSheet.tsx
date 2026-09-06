import { View } from 'react-native';
import type { CitySlot, Construction } from '@castleroyale/contracts';

import { Badge } from '@/shared/components/Badge';
import { BottomSheet } from '@/shared/components/BottomSheet';
import { Text } from '@/shared/components/Text';
import { Timer } from '@/shared/components/Timer';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

export type CitySlotDetailSheetProps = {
  slot: CitySlot | null;
  construction: Construction | null;
  serverTime: string;
  open: boolean;
  onClose: () => void;
  onConstructionFinish: () => void;
};

export function CitySlotDetailSheet({
  slot,
  construction,
  serverTime,
  open,
  onClose,
  onConstructionFinish,
}: CitySlotDetailSheetProps) {
  const theme = useTheme();
  const { t } = useTranslation();
  const building = slot?.building ?? null;
  const showConstruction = building !== null && construction !== null;

  return (
    <BottomSheet
      accessibilityViewIsModal
      enablePanDownToClose
      index={open ? 0 : -1}
      onClose={onClose}
      snapPoints={[`${theme.spacing['3xl'] * 4}%`]}
    >
      <View style={{ gap: theme.spacing.md, padding: theme.spacing.lg }}>
        <Text accessibilityRole="header" variant="heading">
          {t('city.selected_slot')}
        </Text>
        {building === null ? (
          <View style={{ gap: theme.spacing.xs }}>
            <Text>{t('city.slot_empty')}</Text>
            <Text color={theme.color.text.secondary}>{t('city.slot_empty_hint')}</Text>
          </View>
        ) : (
          <View style={{ gap: theme.spacing.xs }}>
            <Text variant="label">{t(building.name_key)}</Text>
            <Text color={theme.color.text.secondary}>{t('building.level', { level: building.level })}</Text>
            <Badge variant="neutral" label={t('city.slot_category', { category: building.category })} />
            {showConstruction && construction && (
              <>
                <Text color={theme.color.text.secondary}>{t('city.construction_finish')}</Text>
                <Timer
                  targetTimestamp={
                    Date.parse(construction.finishes_at) + (Date.now() - Date.parse(serverTime))
                  }
                  onFinish={onConstructionFinish}
                />
              </>
            )}
          </View>
        )}
      </View>
    </BottomSheet>
  );
}
