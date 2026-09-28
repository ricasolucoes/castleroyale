import { getGoogleFirebaseIdentityToken, getFirebaseConfig, isFirebaseConfigured } from '../src/features/auth/firebaseAuth';

describe('Firebase Auth helper', () => {
  it('reads firebase configuration from environment or extra', () => {
    const config = getFirebaseConfig();
    expect(config).toBeDefined();
    expect(typeof isFirebaseConfigured()).toBe('boolean');
  });

  it('resolves fallback identity token in development mode', async () => {
    const defaultToken = await getGoogleFirebaseIdentityToken();
    expect(defaultToken).toBe('jogador.google@ricasolucoes.com.br');

    const emailToken = await getGoogleFirebaseIdentityToken('custom.player@ricasolucoes.com.br');
    expect(emailToken).toBe('custom.player@ricasolucoes.com.br');
  });
});
